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
   * Prüft, ob Authentifizierung erforderlich ist
   *
   * @return bool True, wenn im Entwicklungsmodus, sonst false
   */
  public static function isAuthEnabled(): bool
  {
    if (self::$addon === null) {
      self::$addon = rex_addon::get('rexql');
    }
    return self::$addon->getConfig('require_authentication', true);
  }

  /**
   * Validate domain restrictions
   */
  public static function validateDomainRestrictions(ApiKey $apiKey): bool
  {
    $allowedDomains = $apiKey->getAllowedDomains();

    if (empty($allowedDomains)) {
      return true; // No restrictions
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
   * Validate IP restrictions
   */
  public static function validateIpRestrictions(ApiKey $apiKey): bool
  {
    $allowedIps = $apiKey->getAllowedIps();

    if (empty($allowedIps)) {
      return true; // No restrictions
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

  public static function copyToClipboardButton(string $value): string
  {
    if (empty($value) || $value === null) {
      return '';
    }

    return '<div><button class="btn btn-xs btn-default" data-copy="' . $value . '" title="' . rex_i18n::msg('copy') . '"><i class="fa fa-copy"></i></button></div>';
  }
}
