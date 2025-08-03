<?php

namespace FriendsOfRedaxo\RexQL\Services;

use rex;
use rex_addon_interface;
use rex_logger;

/**
 * Logger service for RexQL
 */
class Logger
{

  private static bool $debugMode = false;
  private static ?rex_logger $instance = null;

  /**
   * Get the singleton instance of the Logger
   *
   * @api
   * @return rex_logger
   */
  public static function getInstance(): rex_logger
  {
    if (self::$instance === null) {
      self::$instance = rex_logger::factory();
      return self::$instance;
    }

    /** @var rex_addon_interface $addon */
    $addon = rex::getProperty('rexql_addon', null);
    self::$debugMode = $addon->getConfig('debug_mode', false);

    return self::$instance;
  }

  /**
   * Log a message
   *
   * @param string|mixed $message The message to log
   * @param string $level Log level (debug, info, warning, error)
   * @param string $file File where the log originated
   * @param int $line Line number where the log originated
   * @param array $context Additional context for the log entry
   */
  public static function log($message, string $level = 'debug', string $file = '', int $line = 0, array $context = []): void
  {
    $logger = self::getInstance();
    if (self::$debugMode || $level === 'error') {
      $safeContext = self::sanitizeContext($context);
      // Use rex_logger to log the message with PSR-3 placeholders - let rex_logger handle interpolation
      // @phpstan-ignore psr3.interpolated
      $logger->log(
        $level,
        (string) $message,
        $safeContext,
        $file ?: __FILE__,
        $line ?: __LINE__
      );
    }
  }

  /**
   * Sanitize context data to prevent injection attacks
   *
   * @param array $context
   * @return array
   */
  private static function sanitizeContext(array $context): array
  {
    $sanitized = [];

    foreach ($context as $key => $value) {
      // Sanitize key - only allow alphanumeric and common chars
      $safeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string) $key);

      // Sanitize value based on type
      if (is_string($value)) {
        // Remove control characters that could be used for injection
        $sanitized[$safeKey] = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
      } elseif (is_scalar($value)) {
        $sanitized[$safeKey] = $value;
      } elseif (is_array($value) || is_object($value)) {
        // Safely encode complex types
        $sanitized[$safeKey] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
      } else {
        $sanitized[$safeKey] = gettype($value);
      }
    }

    return $sanitized;
  }

  /**
   * Log an error with PSR-3 placeholders
   *
   * @api
   * @param string $message Message template with {placeholders}
   * @param array $context Context data for placeholders
   */
  public static function error(string $message, array $context = []): void
  {
    self::log($message, 'error', '', 0, $context);
  }

  /**
   * Log a warning with PSR-3 placeholders
   *
   * @api
   * @param string $message Message template with {placeholders}
   * @param array $context Context data for placeholders
   */
  public static function warning(string $message, array $context = []): void
  {
    self::log($message, 'warning', '', 0, $context);
  }

  /**
   * Log an info message with PSR-3 placeholders
   *
   * @api
   * @param string $message Message template with {placeholders}
   * @param array $context Context data for placeholders
   */
  public static function info(string $message, array $context = []): void
  {
    self::log($message, 'info', '', 0, $context);
  }

  /**
   * Log a debug message with PSR-3 placeholders
   *
   * @api
   * @param string $message Message template with {placeholders}
   * @param array $context Context data for placeholders
   */
  public static function debug(string $message, array $context = []): void
  {
    self::log($message, 'debug', '', 0, $context);
  }
}
