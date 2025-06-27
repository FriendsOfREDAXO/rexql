<?php

namespace FriendsOfRedaxo\RexQL\Api;

use Exception;
use rex_addon;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_file;
use rex_response;

/**
 * Einfaches Session Token System für rexQL
 * 
 * Für Test-Apps und einfache Frontend-Anwendungen
 */
class Auth extends rex_api_function
{
  protected $published = true;

  public function execute()
  {
    rex_response::cleanOutputBuffers();

    try {
      $action = rex_request('action', 'string');

      switch ($action) {
        case 'login':
          return $this->handleLogin();
        case 'validate':
          return $this->handleValidate();
        case 'logout':
          return $this->handleLogout();
        default:
          throw new rex_api_exception('Invalid action');
      }
    } catch (Exception $e) {
      rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
      rex_response::sendContent(json_encode([
        'success' => false,
        'error' => $e->getMessage()
      ]), 'application/json');
    }

    return new rex_api_result(true);
  }

  /**
   * Einfacher Login - generiert Session Token
   */
  private function handleLogin()
  {
    $username = rex_request('username', 'string');
    $password = rex_request('password', 'string');

    // Beispiel: Einfache Benutzer-Validierung
    // In einer echten App würden Sie hier Ihre Datenbank abfragen
    $validUsers = rex_addon::get('rexql')->getConfig('test_users', [
      'testuser' => 'testpass',
      'demo' => 'demo123'
    ]);

    if (!isset($validUsers[$username]) || $validUsers[$username] !== $password) {
      throw new rex_api_exception('Invalid credentials');
    }

    // Session Token generieren
    $sessionToken = $this->generateSessionToken($username);

    // Token speichern
    $this->storeSessionToken($sessionToken, $username);

    rex_response::sendContent(json_encode([
      'success' => true,
      'session_token' => $sessionToken,
      'user' => $username,
      'expires_in' => 3600 // 1 Stunde
    ]), 'application/json');
  }

  /**
   * Session Token validieren
   */
  private function handleValidate()
  {
    $token = rex_request('token', 'string');

    if (empty($token)) {
      throw new rex_api_exception('Token required');
    }

    $sessionData = $this->getSessionData($token);

    if (!$sessionData) {
      throw new rex_api_exception('Invalid or expired token');
    }

    rex_response::sendContent(json_encode([
      'success' => true,
      'user' => $sessionData['user'],
      'created_at' => $sessionData['created_at']
    ]), 'application/json');
  }

  /**
   * Logout - Token invalidieren
   */
  private function handleLogout()
  {
    $token = rex_request('token', 'string');

    if ($token) {
      $this->deleteSessionToken($token);
    }

    rex_response::sendContent(json_encode([
      'success' => true,
      'message' => 'Logged out'
    ]), 'application/json');
  }

  /**
   * Session Token generieren
   */
  private function generateSessionToken(string $username): string
  {
    return 'rexql_session_' . bin2hex(random_bytes(32)) . '_' . time();
  }

  /**
   * Session Token in Cache speichern
   */
  private function storeSessionToken(string $token, string $username): void
  {
    $sessionData = [
      'user' => $username,
      'created_at' => time(),
      'expires_at' => time() + 3600 // 1 Stunde
    ];

    // In File Cache speichern
    $addon = rex_addon::get('rexql');
    $cacheFile = $addon->getCachePath('sessions/' . $token . '.json');
    rex_file::putCache($cacheFile, $sessionData);
  }

  /**
   * Session Daten abrufen
   */
  private function getSessionData(string $token): ?array
  {
    $addon = rex_addon::get('rexql');
    $cacheFile = $addon->getCachePath('sessions/' . $token . '.json');
    $sessionData = rex_file::getCache($cacheFile, null);

    if (!$sessionData) {
      return null;
    }

    // Prüfen ob Token abgelaufen
    if ($sessionData['expires_at'] < time()) {
      $this->deleteSessionToken($token);
      return null;
    }

    return $sessionData;
  }

  /**
   * Session Token löschen
   */
  private function deleteSessionToken(string $token): void
  {
    $addon = rex_addon::get('rexql');
    $cacheFile = $addon->getCachePath('sessions/' . $token . '.json');
    rex_file::delete($cacheFile);
  }

  /**
   * Öffentliche Methode zur Token-Validierung (für Proxy)
   */
  public static function validateSessionToken(string $token): ?array
  {
    $instance = new self();
    return $instance->getSessionData($token);
  }
}
