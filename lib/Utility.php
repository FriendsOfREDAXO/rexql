<?php

namespace FriendsOfRedaxo\RexQL;

use InvalidArgumentException;

use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Error\SyntaxError;

use rex;
use rex_yrewrite;
use rex_addon;
use rex_i18n;
use rex_fragment;
use rex_logger;
use rex_log_file;

enum EndpointType: string
{
  case Endpoint = 'rexql';
  case Proxy = 'rexql_proxy';
  case Auth = 'rexql_auth';
}

class Utility
{

  static protected $addon = null;

  /**
   * Checks if YRewrite is enabled
   *
   * @return bool
   */
  public static function isYRewriteEnabled(): bool
  {
    return rex_addon::get('yrewrite')->isInstalled() && rex_addon::get('yrewrite')->isAvailable();
  }

  /**
   * Generates the endpoint URL for rexQL
   *
   * @return string Endpoint URL
   */
  public static function getEndpointUrl(EndpointType $type = EndpointType::Endpoint, bool $short = false): string
  {

    if (self::$addon === null) {
      self::$addon = rex_addon::get('rexql');
    }

    // Custom endpoint URL from configuration
    $endpointUrl = self::$addon->getConfig($type->value . '_url', '');
    if ($endpointUrl !== '') {
      $baseUrl = self::isYRewriteEnabled() ? rtrim(rex_yrewrite::getFullPath(), '/') : rtrim(rex::getServer(), '/');
      if ($short) {
        return $baseUrl . '/api/' . $type->value;
      }
      return $baseUrl . '/' . ltrim($endpointUrl, '/');
    }

    // Default endpoint URL
    $baseUrl = self::isYRewriteEnabled() ? rtrim(rex_yrewrite::getFullPath(), '/') : rtrim(rex::getServer(), '/');

    // If no server URL is available, use relative path
    if (empty($baseUrl) || $baseUrl === 'http://.') {
      $baseUrl = '';
    }
    if ($short) {
      return $baseUrl . '/api/' . $type->value;
    }
    return $baseUrl . '/index.php?rex-api-call=' . $type->value;
  }


  /**
   * Checks if authentication is required
   *
   * @return bool True, when in development mode, otherwise false
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
  public static function validateDomainRestrictions(?ApiKey $apiKey): bool
  {
    if (!$apiKey) {
      return true; // No API key, no restrictions
    }

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

    $clientIp = self::getClientIp();
    if (empty($clientIp)) {
      return false;
    }

    foreach ($allowedIps as $allowedIp) {
      $allowedIp = trim($allowedIp);

      // Exact match or CIDR-Notation
      if ($clientIp === $allowedIp || self::isIpInRange($clientIp, $allowedIp)) {
        return true;
      }
    }

    return false;
  }

  /**
   * get client IP address
   */
  private static function getClientIp(): string
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

  /**
   * Validate HTTPS restrictions
   */
  public static function validateHttpsRestrictions(ApiKey $apiKey): bool
  {
    if (!$apiKey->isHttpsOnly()) {
      return true; // HTTPS not required
    }

    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  }

  /**
   * Checks if an IP is within a CIDR range
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

  public static function clearRexSystemLog(): void
  {
    rex_logger::close();
    $logFile = rex_logger::getPath();
    rex_log_file::delete($logFile);
  }

  public static function snakeCaseToCamelCase(string $value): string
  {
    $output = '';
    $parts = explode('_', $value);
    $firstPart = array_shift($parts);
    foreach ($parts as $part) {
      $output .= ucfirst($part);
    }
    return $firstPart . $output;
  }


  public static function camelCaseToSnakeCase(string $input): string
  {
    // return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
  }

  public static function deconstruct(array $args, array $keys): array
  {
    // Remove specified keys from the args array and return the rest
    return array_keys($args) !== range(0, count($args) - 1) ? array_diff_key($args, array_flip($keys)) : array_diff($args, $keys);
  }

  public static function formatGraphQLQuery($query)
  {
    try {
      // Extract comments and separate GraphQL content
      $comments = [];
      $lines = explode("\n", $query);
      $graphqlContent = [];

      foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (strpos($line, '#') === 0) {
          // This is a comment line
          // Check if there's GraphQL content after the comment
          if (preg_match('/^#.*?:\s*(.+)$/', $line, $matches)) {
            $comments[] = substr($line, 0, strpos($line, ':') + 1);
            $graphqlContent[] = trim($matches[1]);
          } else {
            $comments[] = $line;
          }
        } else {
          $graphqlContent[] = $line;
        }
      }

      $cleanQuery = implode(' ', $graphqlContent);
      $cleanQuery = trim($cleanQuery);

      // Parse the query into an AST
      $ast = Parser::parse($cleanQuery);

      // Print the AST back to a formatted string
      $formatted = Printer::doPrint($ast);

      // Prepend comments if they existed
      if (!empty($comments)) {
        $formatted = implode("\n", $comments) . "\n" . $formatted;
      }

      return $formatted;
    } catch (SyntaxError $e) {
      throw new InvalidArgumentException("Invalid GraphQL query: " . $e->getMessage());
    }
  }

  public static function getFragment($file, $vars = [])
  {
    $fragment = new rex_fragment();
    foreach ($vars as $key => $value) {
      $fragment->setVar($key, $value, false);
    }
    return $fragment->parse($file . ".php");
  }
}
