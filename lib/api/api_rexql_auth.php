<?php

/**
 * Simple session token system for rexQL
 * 
 * For test apps and simple frontend applications
 */
class rex_api_rexql_auth extends rex_api_function
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
   * Simple login - generates session token
   */
  private function handleLogin()
  {
    $username = rex_request('username', 'string');
    $password = rex_request('password', 'string');

    // Example: Simple user validation
    // In a real app you would query your database here
    $validUsers = rex_addon::get('rexql')->getConfig('test_users', [
      'testuser' => 'testpass',
      'demo' => 'demo123'
    ]);

    if (!isset($validUsers[$username]) || $validUsers[$username] !== $password) {
      throw new rex_api_exception('Invalid credentials');
    }

    // Generate session token
    $sessionToken = $this->generateSessionToken($username);

    // Save session token in cache
    $this->storeSessionToken($sessionToken, $username);

    rex_response::sendContent(json_encode([
      'success' => true,
      'session_token' => $sessionToken,
      'user' => $username,
      'expires_in' => 3600 // 1 Stunde
    ]), 'application/json');
  }

  /**
   * Validate session token
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
   * Invalidate session token (logout)
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
   * Generate a unique session token
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
   * Get Session Data by Token
   */
  private function getSessionData(string $token): ?array
  {
    $addon = rex_addon::get('rexql');
    $cacheFile = $addon->getCachePath('sessions/' . $token . '.json');
    $sessionData = rex_file::getCache($cacheFile, null);

    if (!$sessionData) {
      return null;
    }

    // Check if token is expired
    if ($sessionData['expires_at'] < time()) {
      $this->deleteSessionToken($token);
      return null;
    }

    return $sessionData;
  }

  /**
   * Delete Session Token
   */
  private function deleteSessionToken(string $token): void
  {
    $addon = rex_addon::get('rexql');
    $cacheFile = $addon->getCachePath('sessions/' . $token . '.json');
    rex_file::delete($cacheFile);
  }

  /**
   * Public method to validate session token (for Proxy)
   */
  public static function validateSessionToken(string $token): ?array
  {
    $instance = new self();
    return $instance->getSessionData($token);
  }
}
