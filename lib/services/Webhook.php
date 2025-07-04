<?php

namespace FriendsOfRedaxo\RexQL;

use rex_logger;
use rex_addon;

/**
 * Webhook service for sending HTTP requests to external endpoints
 */
class Webhook
{
  private static ?rex_addon $addon = null;
  private static ?rex_logger $logger = null;
  private static $loggerContext = '; rexql webhook';
  private static $isDevMode = false;

  public static function init()
  {
    self::$addon = rex_addon::get('rexql');
    self::$isDevMode = self::$addon->getConfig('dev_mode', false);
    self::$logger = rex_logger::factory();
  }

  /**
   * Send webhook request for a specific event
   * 
   * @param string $event The event name (e.g., 'ART_ADDED')
   * @param array $data The event data to send
   * @return bool Success status
   */
  public static function send(string $event, array $data): bool
  {
    if (!self::$addon) {
      self::init();
    }

    // Get all active webhooks
    $sql = \rex_sql::factory();
    $sql->setQuery('SELECT * FROM ' . \rex::getTable('rexql_webhook') . ' WHERE active = 1');

    $success = true;
    foreach ($sql->getArray() as $webhook) {
      $payload = self::buildPayload($event, $data);
      $result = self::sendToWebhook($webhook, $payload);
      if (!$result['success']) {
        $success = false;
      }
    }

    return $success;
  }

  /**
   * Build enhanced payload with normalized names and additional context
   * 
   * @param string $event The event name
   * @param array $data The event data
   * @return array The enhanced payload
   */
  private static function buildPayload(string $event, array $data): array
  {
    $payload = [
      'event' => $event,
      'timestamp' => time(),
      'data' => $data,
      'source' => 'rexql',
      'site_url' => \rex::getServer(),
    ];

    // Add normalized names and additional context based on event type
    if (strpos($event, 'ART_') === 0) {
      $payload['data']['table_name'] = 'rex_article';
      if (isset($data['subject'])) {
        $payload['data']['tag'] = self::getNormalizedArticleName($data['subject']);
      }
    } elseif (strpos($event, 'CAT_') === 0) {
      $payload['data']['table_name'] = 'rex_category';
      if (isset($data['subject'])) {
        $payload['data']['tag'] = self::getNormalizedCategoryName($data['subject']);
      }
    } elseif (strpos($event, 'YFORM_') === 0) {
      if (isset($data['subject'])) {
        $payload['data']['tag'] = is_object($data['subject']) ? $data['subject']->getTableName() : 'unknown';
        $payload['data']['id'] = is_object($data['subject']) ? $data['subject']->getId() : $data['subject'];
      }
    }

    return $payload;
  }

  /**
   * Get normalized article name/slug
   * 
   * @param mixed $subject The article object or ID
   * @return string The normalized name
   */
  private static function getNormalizedArticleName($subject): string
  {
    if (is_object($subject) && method_exists($subject, 'getName')) {
      $name = $subject->getName();
    } elseif (is_numeric($subject)) {
      $article = \rex_article::get($subject);
      $name = $article ? $article->getName() : 'unknown';
    } else {
      return 'unknown';
    }

    return self::normalizeSlug($name);
  }

  /**
   * Get normalized category name/slug
   * 
   * @param mixed $subject The category object or ID
   * @return string The normalized name
   */
  private static function getNormalizedCategoryName($subject): string
  {
    if (is_object($subject) && method_exists($subject, 'getName')) {
      $name = $subject->getName();
    } elseif (is_numeric($subject)) {
      $category = \rex_category::get($subject);
      $name = $category ? $category->getName() : 'unknown';
    } else {
      return 'unknown';
    }

    return self::normalizeSlug($name);
  }

  /**
   * Normalize a string to a URL-friendly slug
   * 
   * @param string $text The text to normalize
   * @return string The normalized slug
   */
  private static function normalizeSlug(string $text): string
  {
    return strtolower(str_replace(
      [' ', 'ä', 'ö', 'ü', 'ß'],
      ['-', 'ae', 'oe', 'ue', 'ss'],
      $text
    ));
  }

  /**
   * Send webhook to a specific webhook configuration
   * 
   * @param array $webhook The webhook configuration
   * @param array $payload The payload to send
   * @return array Result with success status and message
   */
  public static function sendToWebhook(array $webhook, array $payload): array
  {
    if (!self::$addon) {
      self::init();
    }

    $url = $webhook['url'];
    $secret = $webhook['secret'];
    $timeout = $webhook['timeout'] ?? 30;
    $callCount = $webhook['call_count'] ?? 0;
    $retryAttempts = $webhook['retry_attempts'] ?? 3;

    // Update webhook call statistics
    self::updateWebhookStats($webhook['id'], $callCount);

    for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
      $success = self::performRequest($url, json_encode($payload), [
        'Content-Type: application/json',
        'X-Webhook-Signature: sha256=' . hash_hmac('sha256', json_encode($payload), $secret),
        'X-Webhook-Secret: ' . $secret,
        'User-Agent: REDAXO-rexQL-Webhook/1.0',
      ], $timeout);

      if ($success) {
        if (self::$isDevMode && self::$logger)
          self::$logger->log('info', "Webhook sent successfully (attempt {$attempt})" . self::$loggerContext . '; url: ' . $url . '; payload:' . $payload, [], __FILE__, __LINE__);
        self::updateWebhookStatus($webhook['id'], 'success');
        return ['success' => true, 'message' => 'Webhook sent successfully'];
      }

      if ($attempt < $retryAttempts) {
        sleep(pow(2, $attempt - 1)); // Exponential backoff
      }
    }

    self::updateWebhookStatus($webhook['id'], 'failed');
    return ['success' => false, 'message' => 'Webhook failed after ' . $retryAttempts . ' attempts'];
  }

  /**
   * Update webhook call statistics
   * 
   * @param int $webhookId The webhook ID
   */
  private static function updateWebhookStats(int $webhookId, int $callCount): void
  {
    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_webhook'));
    $sql->setWhere(['id' => $webhookId]);
    $sql->setValue('call_count', $callCount + 1);
    $sql->setValue('last_called', date('Y-m-d H:i:s'));
    $sql->update();
  }

  /**
   * Update webhook status after call
   * 
   * @param int $webhookId The webhook ID
   * @param string $status The status to set
   * @param string $error Optional error message
   */
  private static function updateWebhookStatus(int $webhookId, string $status, string $error = ''): void
  {
    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_webhook'));
    $sql->setWhere(['id' => $webhookId]);
    $sql->setValue('last_status', $status);
    if (!empty($error)) {
      $sql->setValue('last_error', $error);
    }
    $sql->update();
  }

  /**
   * Send HTTP request to webhook endpoint (legacy method for backward compatibility)
   * 
   * @param string $url The webhook URL
   * @param string $secret The webhook secret
   * @param array $payload The data to send
   * @return bool Success status
   */
  private static function sendRequest(string $url, string $secret, array $payload): bool
  {
    $jsonPayload = json_encode($payload);
    $signature = hash_hmac('sha256', $jsonPayload, $secret);

    $headers = [
      'Content-Type: application/json',
      'X-Webhook-Signature: sha256=' . $signature,
      'X-Webhook-Secret: ' . $secret,
      'User-Agent: REDAXO-rexQL-Webhook/1.0',
    ];

    $timeout = self::$addon->getConfig('webhook_timeout', 30);
    $retryAttempts = self::$addon->getConfig('webhook_retry_attempts', 3);

    for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
      $success = self::performRequest($url, $jsonPayload, $headers, $attempt === 1 ? 0 : $timeout);

      if ($success) {
        if (self::$isDevMode && self::$logger) {
          self::$logger->log('info', "Webhook sent successfully (attempt {$attempt})" . self::$loggerContext . '; url: ' . $url, [], __FILE__, __LINE__);
        }
        return true;
      }

      if ($attempt < $retryAttempts) {
        // Wait before retry (exponential backoff)
        sleep(pow(2, $attempt - 1));
      }
    }
    if (self::$isDevMode && self::$logger)
      self::$logger->log('info', "Failed to send webhook after {$retryAttempts}" . self::$loggerContext . '; url: ' . $url, [], __FILE__, __LINE__);

    return false;
  }

  /**
   * Perform the actual HTTP request
   * 
   * @param string $url
   * @param string $payload
   * @param array $headers
   * @param int $timeout
   * @return bool
   */
  private static function performRequest(string $url, string $payload, array $headers, int $timeout): bool
  {
    // Use cURL for HTTP request
    $ch = curl_init();

    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($response === false || !empty($error)) {
      if (self::$isDevMode && self::$logger)
        self::$logger->log('info', "cURL error" . self::$loggerContext . '; error: ' . $error, [], __FILE__, __LINE__);
      return false;
    }

    // Consider 2xx status codes as success
    if ($httpCode >= 200 && $httpCode < 300) {
      return true;
    }

    if (self::$isDevMode && self::$logger)
      self::$logger->log('info', "HTTP error ($httpCode) response: $response; payload " . \json_encode($payload) . self::$loggerContext . '; url: ' . $url, [], __FILE__, __LINE__);
    return false;
  }

  /**
   * Test webhook connection
   * 
   * @return array Test result with status and message
   */
  public static function test(): array
  {
    if (!self::$addon) {
      self::init();
    }

    $url = self::$addon->getConfig('webhook_url');
    $secret = self::$addon->getConfig('webhook_secret');

    if (empty($url) || empty($secret)) {
      return [
        'success' => false,
        'message' => 'Webhook URL or secret not configured'
      ];
    }

    $testPayload = [
      'event' => 'TEST',
      'timestamp' => time(),
      'data' => ['test' => true],
      'source' => 'rexql',
      'site_url' => \rex::getServer(),
    ];

    $success = self::sendRequest($url, $secret, $testPayload);

    return [
      'success' => $success,
      'message' => $success ? 'Webhook test successful' : 'Webhook test failed'
    ];
  }

  /**
   * Test a specific webhook configuration
   * 
   * @param array $webhook The webhook configuration
   * @return array Test result with status and message
   */
  public static function testWebhook(array $webhook): array
  {

    $testPayload = self::buildPayload('TEST', [
      'tag' => 'all',
    ]);

    return self::sendToWebhook($webhook, $testPayload);
  }
}
