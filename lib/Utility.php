<?php

namespace FriendsOfRedaxo\RexQL;

use rex;
use rex_yrewrite;
use rex_addon;
use rex_ydeploy;
use rex_path;
use rex_i18n;

/**
 * GraphQL Schema Builder für REDAXO
 */

class Utility
{

  static protected $addon = null;
  static protected $endpointUrl = '';

  /**
   * Prüft, ob YRewrite aktiviert ist
   *
   * @return bool
   */
  public static function isYRewriteEnabled(): bool
  {
    return rex_addon::get('yrewrite')->isInstalled() && rex_addon::get('yrewrite')->isAvailable();
  }

  /**
   * Generiert die Endpoint-URL für rexQL
   *
   * @return string Endpoint URL
   */
  public static function getEndpointUrl(): string
  {
    if (self::$endpointUrl !== '') {
      return self::$endpointUrl;
    }

    if (self::$addon === null) {
      self::$addon = rex_addon::get('rexql');
    }

    // Custom endpoint URL aus Konfiguration
    $endpointUrl = self::$addon->getConfig('endpoint_url', '');
    if ($endpointUrl !== '') {
      $baseUrl = self::isYRewriteEnabled() ? rtrim(rex_yrewrite::getFullPath(), '/') : rtrim(rex::getServer(), '/');
      self::$endpointUrl = $baseUrl . '/' . ltrim($endpointUrl, '/');
      return self::$endpointUrl;
    }

    // Standard API-Endpoint verwenden
    $baseUrl = self::isYRewriteEnabled() ? rtrim(rex_yrewrite::getFullPath(), '/') : rtrim(rex::getServer(), '/');

    // Falls kein Server-URL verfügbar, verwende relativen Pfad
    if (empty($baseUrl) || $baseUrl === 'http://.') {
      $baseUrl = '';
    }

    self::$endpointUrl = $baseUrl . '/index.php?rex-api-call=rexql_graphql';
    return self::$endpointUrl;
  }


  /**
   * Prüft, ob die REDAXO-Installation im Entwicklungsmodus ist
   *
   * @return bool True, wenn im Entwicklungsmodus, sonst false
   */
  public static function isDevMode(): bool
  {

    // Check environment variable
    if (getenv('REDAXO_DEV_MODE') === '1') {
      return true;
    }

    // Check .env.local file for mode variable
    $envFile = rex_path::base('.env.local');
    if (file_exists($envFile)) {
      $envContent = file_get_contents($envFile);
      if (preg_match('/^mode\s*=\s*dev/m', $envContent)) {
        return true;
      }
    }

    // Check if ydeploy addon is available and check deployment status
    if (rex_addon::get('ydeploy')->isAvailable()) {
      $ydeploy = rex_ydeploy::factory();
      // If not deployed, allow (development environment)
      if (!$ydeploy->isDeployed()) {
        return true;
      }
    }

    return false;
  }

  /**
   * Domain-Beschränkungen validieren
   */
  public static function validateDomainRestrictions(ApiKey $apiKey): bool
  {
    $allowedDomains = $apiKey->getAllowedDomains();

    if (empty($allowedDomains)) {
      return true; // Keine Beschränkungen
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($origin)) {
      return false;
    }

    $domain = parse_url($origin, PHP_URL_HOST);

    foreach ($allowedDomains as $allowedDomain) {
      if ($domain === trim($allowedDomain) || fnmatch(trim($allowedDomain), $domain)) {
        return true;
      }
    }

    return false;
  }

  /**
   * IP-Beschränkungen validieren
   */
  public static function validateIpRestrictions(ApiKey $apiKey): bool
  {
    $allowedIps = $apiKey->getAllowedIps();

    if (empty($allowedIps)) {
      return true; // Keine Beschränkungen
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($clientIp)) {
      return false;
    }

    foreach ($allowedIps as $allowedIp) {
      $allowedIp = trim($allowedIp);

      // Exact match oder CIDR-Notation
      if ($clientIp === $allowedIp || self::isIpInRange($clientIp, $allowedIp)) {
        return true;
      }
    }

    return false;
  }

  /**
   * HTTPS-Beschränkungen validieren
   */
  public static function validateHttpsRestrictions(ApiKey $apiKey): bool
  {
    if (!$apiKey->isHttpsOnly()) {
      return true; // HTTPS nicht erforderlich
    }

    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  }

  /**
   * Prüft ob IP in einem CIDR-Bereich liegt
   */
  public static function isIpInRange(string $ip, string $range): bool
  {
    if (!str_contains($range, '/')) {
      return false;
    }

    list($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;

    return ($ip & $mask) === $subnet;
  }

  public static function copyToClipboardButton(string|null $text = ''): string
  {
    if (empty($text) || $text === null) {
      return '';
    }

    return '<div><button class="btn btn-xs btn-default" onclick="copyToClipboard(\'' . htmlspecialchars($text, ENT_QUOTES) . '\')" title="' . rex_i18n::msg('copy') . '"><i class="fa fa-copy"></i></button></div>';
  }
}
