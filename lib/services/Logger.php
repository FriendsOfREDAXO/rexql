<?php

namespace FriendsOfRedaxo\RexQL\Services;

use rex_logger;

/**
 * Logger service for RexQL
 */
class Logger
{
  private static ?rex_logger $instance = null;

  /**
   * Get the singleton instance of the Logger
   *
   * @return rex_logger
   */
  public static function getInstance(): rex_logger
  {
    if (self::$instance === null) {
      self::$instance = rex_logger::factory();
    }
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
    // Use rex_logger to log the message with correct parameter order
    $logger->log(
      $level,
      $message,
      $context,
      $file ?: __FILE__,
      $line ?: __LINE__
    );
  }

  /**
   * Log an error
   *
   * @param string|mixed $message The message to log
   */
  public static function error($message): void
  {
    self::log($message, 'error');
  }

  /**
   * Log a warning
   *
   * @param string|mixed $message The message to log
   */
  public static function warning($message): void
  {
    self::log($message, 'warning');
  }

  /**
   * Log an info message
   *
   * @param string|mixed $message The message to log
   */
  public static function info($message): void
  {
    self::log($message, 'info');
  }

  /**
   * Log a debug message
   *
   * @param string|mixed $message The message to log
   */
  public static function debug($message): void
  {
    self::log($message, 'debug');
  }
}
