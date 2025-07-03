<?php

namespace FriendsOfRedaxo\RexQL;

/**
 * Enhanced API Key mit Domain-Restrictions und anderen Security Features
 * 
 * Mögliche Erweiterungen für bessere Client-Side Sicherheit:
 */
class EnhancedApiKey extends ApiKey
{
  private array $allowedDomains = [];
  private array $allowedIps = [];
  private bool $httpsOnly = false;
  private int $tokenLifetime = 3600; // 1 Stunde

  /**
   * Domain-Restriction prüfen
   */
  public function validateDomain(): bool
  {
    if (empty($this->allowedDomains)) {
      return true; // Keine Restriction
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    foreach ($this->allowedDomains as $domain) {
      if (str_contains($origin, $domain) || str_contains($referer, $domain)) {
        return true;
      }
    }

    return false;
  }

  /**
   * IP-Restriction prüfen
   */
  public function validateIp(): bool
  {
    if (empty($this->allowedIps)) {
      return true;
    }

    $clientIp = $this->getClientIp();
    return in_array($clientIp, $this->allowedIps);
  }

  /**
   * HTTPS-Only prüfen
   */
  public function validateHttps(): bool
  {
    if (!$this->httpsOnly) {
      return true;
    }

    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
  }

  /**
   * Temporären Token generieren
   */
  public function generateTemporaryToken(array $permissions = []): string
  {
    $payload = [
      'api_key_id' => $this->getId(),
      'permissions' => $permissions ?: $this->getPermissions(),
      'exp' => time() + $this->tokenLifetime,
      'iat' => time()
    ];

    // JWT oder eigenes Token-System
    return $this->encodeToken($payload);
  }

  /**
   * Client IP ermitteln
   */
  private function getClientIp(): string
  {
    $headers = [
      'HTTP_CF_CONNECTING_IP',     // Cloudflare
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
      if (!empty($_SERVER[$header])) {
        $ips = explode(',', $_SERVER[$header]);
        return trim($ips[0]);
      }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '';
  }

  private function encodeToken(array $payload): string
  {
    // Implementation of a secure token system
    // Could use JWT or custom system
    return base64_encode(json_encode($payload));
  }
}
