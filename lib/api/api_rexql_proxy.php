<?php

/**
 * Backend Proxy für rexQL GraphQL API
 * 
 * Ermöglicht sichere Frontend-Integration ohne Exposition der API-Schlüssel
 */
class rex_api_rexql_proxy extends rex_api_function
{
  protected $published = true;

  public function execute()
  {
    // Verhindern, dass der normale REDAXO Response-Zyklus ausgeführt wird
    rex_response::cleanOutputBuffers();

    try {
      $addon = rex_addon::get('rexql');

      // Proxy nur aktivieren wenn explizit erlaubt
      if (!$addon->getConfig('proxy_enabled', false)) {
        throw new rex_api_exception('GraphQL Proxy ist nicht aktiviert');
      }

      // Session-basierte Authentifizierung
      $sessionToken = $this->validateSession();

      // Public Key aus Request
      $publicKey = $this->getPublicKey();
      if (!$publicKey) {
        throw new rex_api_exception('Public Key erforderlich');
      }

      // API Key anhand Public Key finden
      $apiKey = FriendsOfRedaxo\RexQL\ApiKey::findByKey($publicKey);
      if (!$apiKey || $apiKey->getKeyType() !== 'public_private') {
        throw new rex_api_exception('Ungültiger Public Key');
      }

      // Domain-Validierung
      if (!FriendsOfRedaxo\RexQL\Utility::validateDomainRestrictions($apiKey)) {
        throw new rex_api_exception('Domain nicht erlaubt');
      }

      // IP-Validierung
      if (!FriendsOfRedaxo\RexQL\Utility::validateIpRestrictions($apiKey)) {
        throw new rex_api_exception('IP-Adresse nicht erlaubt');
      }

      // HTTPS-Validierung
      if (!FriendsOfRedaxo\RexQL\Utility::validateHttpsRestrictions($apiKey)) {
        throw new rex_api_exception('HTTPS erforderlich');
      }

      // GraphQL Input parsen
      $input = $this->getGraphQLInput();

      // Request an rexQL weiterleiten mit Private Key
      $response = $this->forwardToRexQL($input, $apiKey->getPrivateKey());

      // Response zurückgeben
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendContent($response, 'application/json');
    } catch (Exception $e) {
      rex_response::setStatus($e instanceof rex_api_exception ? rex_response::HTTP_BAD_REQUEST : rex_response::HTTP_INTERNAL_ERROR);
      rex_response::sendContent(json_encode([
        'errors' => [['message' => $e->getMessage()]]
      ]), 'application/json');
    }

    return new rex_api_result(true);
  }

  /**
   * Session validieren (nur Custom Session Tokens)
   */
  private function validateSession(): string
  {
    // Custom Session Token (z.B. Frontend Login)
    $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $sessionToken = str_replace('Bearer ', '', $sessionToken);

    if (empty($sessionToken)) {
      throw new rex_api_exception('Session Token erforderlich');
    }

    // Hier würden Sie Ihre eigene Session-Validierung implementieren
    // z.B. JWT Token validieren oder eigenes Session-System
    if (!$this->validateCustomSessionToken($sessionToken)) {
      throw new rex_api_exception('Ungültiger Session Token');
    }

    return $sessionToken;
  }
  /**
   * Custom Session Token validieren
   */
  private function validateCustomSessionToken(string $token): bool
  {
    // Verwende das neue Auth-System
    $sessionData = rex_api_rexql_auth::validateSessionToken($token);
    return $sessionData !== null;
  }

  /**
   * Public Key aus Request ermitteln
   */
  private function getPublicKey(): ?string
  {
    return rex_request('public_key', 'string') ?: ($_SERVER['HTTP_X_PUBLIC_KEY'] ?? null);
  }

  /**
   * GraphQL Input aus Request parsen
   */
  private function getGraphQLInput(): array
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      throw new rex_api_exception('Nur POST Requests erlaubt');
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_contains($contentType, 'application/json')) {
      throw new rex_api_exception('Content-Type application/json erforderlich');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new rex_api_exception('Ungültiges JSON in Request Body');
    }

    return $input;
  }

  /**
   * Request an rexQL weiterleiten
   */
  private function forwardToRexQL(array $input, string $privateKey): string
  {
    $baseUrl = rex_addon::get('rexql')->getConfig('base_url', rex::getServer());
    $url = rtrim($baseUrl, '/') . '/index.php?rex-api-call=rexql_graphql';

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($input),
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-KEY: ' . $privateKey
      ],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
      curl_close($ch);
      throw new Exception('Fehler beim Weiterleiten der Anfrage: ' . curl_error($ch));
    }

    curl_close($ch);

    if ($httpCode !== 200) {
      throw new Exception('GraphQL API Fehler (HTTP ' . $httpCode . ')');
    }

    return $response;
  }
}
