<?php

namespace FriendsOfRedaxo\RexQL\Api;

use FriendsOfRedaxo\RexQL\Utility;
use FriendsOfRedaxo\RexQL\ApiKey;

use rex;
use rex_addon;
use rex_api_function;
use rex_api_exception;
use rex_api_result;
use rex_response;


/**
 * Backend proxy for rexQL GraphQL API
 * 
 * Enables secure frontend integration without exposing API keys
 */


class rex_api_rexql_proxy extends rex_api_function
{
  protected $published = true;

  public function execute()
  {
    // Prevent normal REDAXO response cycle from executing
    rex_response::cleanOutputBuffers();

    try {
      $addon = rex_addon::get('rexql');

      // Only enable proxy if explicitly allowed
      if (!$addon->getConfig('proxy_enabled', false)) {
        throw new rex_api_exception('GraphQL Proxy is not enabled');
      }

      // Session-based authentication
      $sessionToken = $this->validateSession();

      // Public Key from request
      $publicKey = $this->getPublicKey();
      if (!$publicKey) {
        throw new rex_api_exception('Public Key erforderlich');
      }

      // Find API Key by Public Private Key
      $apiKey = ApiKey::findByKey($publicKey);
      if (!$apiKey || $apiKey->getKeyType() !== 'public_private') {
        throw new rex_api_exception('Ungültiger Public Key');
      }

      // Validate domain restrictions
      if (!Utility::validateDomainRestrictions($apiKey)) {
        throw new rex_api_exception('Domain nicht erlaubt');
      }

      // Validate IP restrictions
      if (!Utility::validateIpRestrictions($apiKey)) {
        throw new rex_api_exception('IP-Adresse nicht erlaubt');
      }

      // Validate HTTPS restrictions
      if (!Utility::validateHttpsRestrictions($apiKey)) {
        throw new rex_api_exception('HTTPS erforderlich');
      }

      // parse GraphQL input
      $input = $this->getGraphQLInput();

      // Redirect request to rexQL with Private Key
      $response = $this->forwardToRexQL($input, $apiKey->getPrivateKey());

      // Return response
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendContent($response, 'application/json');
    } catch (\Exception $e) {
      rex_response::setStatus($e instanceof rex_api_exception ? rex_response::HTTP_BAD_REQUEST : rex_response::HTTP_INTERNAL_ERROR);
      rex_response::sendContent(json_encode([
        'errors' => [['message' => $e->getMessage()]]
      ]), 'application/json');
    }

    return new rex_api_result(true);
  }

  /**
   * Validate session token (only for Custom Session Tokens)
   */
  private function validateSession(): string
  {
    // Custom Session Token (ex. frontend login)
    $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $sessionToken = str_replace('Bearer ', '', $sessionToken);

    if (empty($sessionToken)) {
      throw new rex_api_exception('Session token required');
    }

    // Here you would implement your own session validation
    // e.g. validate JWT token or your own session system
    if (!$this->validateCustomSessionToken($sessionToken)) {
      throw new rex_api_exception('Invalid session token');
    }

    return $sessionToken;
  }
  /**
   * Validate custom session token
   */
  private function validateCustomSessionToken(string $token): bool
  {
    // Verwende das neue Auth-System
    $sessionData = rex_api_rexql_auth::validateSessionToken($token);
    return $sessionData !== null;
  }

  /**
   * Get Public Key from request
   */
  private function getPublicKey(): ?string
  {
    return rex_request('public_key', 'string') ?: ($_SERVER['HTTP_X_PUBLIC_KEY'] ?? null);
  }

  /**
   * Parse GraphQL input from request body
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
   * Forward request to rexQL GraphQL API
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
      throw new \Exception('Fehler beim Weiterleiten der Anfrage: ' . curl_error($ch));
    }

    curl_close($ch);

    if ($httpCode !== 200) {
      throw new \Exception('GraphQL API Fehler (HTTP ' . $httpCode . ')');
    }

    return $response;
  }
}
