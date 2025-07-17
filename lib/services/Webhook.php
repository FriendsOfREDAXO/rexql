<?php

namespace FriendsOfRedaxo\RexQL;

use rex_addon;
use rex_article;
use rex_logger;
use rex_response;
use rex_string;

/**
 * Webhook service for sending HTTP requests to external endpoints
 */
class Webhook
{
  private static ?rex_addon $addon = null;
  private static ?rex_logger $logger = null;
  private static $loggerContext = '; rexql webhook';
  private static $isDevMode = true;

  public static function init()
  {
    // Utility::deleteRexSystemLog();

    self::$addon = rex_addon::get('rexql');
    self::$isDevMode = self::$addon->getConfig('debug_mode', false);
    self::$logger = rex_logger::factory();
  }

  /**
   * Send webhook request for a specific event
   * 
   * @param string $event The event name (e.g., 'ART_ADDED')
   * @param array $data The event data to send
   * @return bool Success status
   */
  public static function send(array $params): bool
  {
    if (!self::$addon) {
      self::init();
    }

    // Get all active webhooks
    $sql = \rex_sql::factory();
    $sql->setQuery('SELECT * FROM ' . \rex::getTable('rexql_webhook') . ' WHERE active = 1');

    $success = true;
    foreach ($sql->getArray() as $webhook) {
      $payload = self::buildPayload($params['extension_point'], $params);
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
  private static function buildPayload(string $event, array $params): array
  {
    $payload = [
      'event' => $event,
      'timestamp' => time(),
      'data' => [],
      'source' => 'rexql',
      'site_url' => \rex::getServer(),
    ];

    // Add normalized names and additional context based on event type
    switch ($event) {
      case 'ART_ADDED':
      case 'ART_UPDATED':
      case 'ART_DELETED':
      case 'ART_MOVED':
      case 'ART_STATUS':
      case 'ART_SLICES_COPY':
      case 'CAT_ADDED':
      case 'CAT_UPDATED':
      case 'CAT_DELETED':
      case 'CAT_MOVED':
      case 'CAT_STATUS':
        $payload['data']['table_name'] = 'rex_article';
        $payload['data']['tag'] = self::getNormalizedName($params['name']);
        break;
      case 'SLICE_ADDED':
      case 'SLICE_UPDATE':
      case 'SLICE_DELETE':
      case 'SLICE_MOVE':
      case 'SLICE_STATUS':
        $payload['data']['table_name'] = 'rex_article_slice';
        $payload['data']['tag'] = self::getNormalizedNameById($params['article_id']);
        break;
      case 'YFORM_DATA_ADDED':
      case 'YFORM_DATA_UPDATED':
      case 'YFORM_DATA_DELETED':
        $payload['data']['table_name'] = $params['subject']->getTableName();
        break;
      default:
        $payload['data']['tag'] = 'all';
        break;
    }
    // if (strpos($event, 'ART_') === 0) {
    //   $payload['data']['table_name'] = 'rex_article';
    //   if (isset($data['subject'])) {
    //     $payload['data']['tag'] = self::getNormalizedArticleName($data['subject']);
    //   }
    // } elseif (strpos($event, 'CAT_') === 0) {
    //   $payload['data']['table_name'] = 'rex_category';
    //   if (isset($data['subject'])) {
    //     $payload['data']['tag'] = self::getNormalizedCategoryName($data['subject']);
    //   }
    // } elseif (strpos($event, 'YFORM_') === 0) {
    //   if (isset($data['subject'])) {
    //     $payload['data']['tag'] = is_object($data['subject']) ? $data['subject']->getTableName() : 'unknown';
    //     $payload['data']['id'] = is_object($data['subject']) ? $data['subject']->getId() : $data['subject'];
    //   }
    // } else {
    //   $payload['data']['tag'] = 'all';
    // }
    self::$logger->log('info', "Sending webhook for event: {$event}" . self::$loggerContext . '; data: ' . print_r($payload, true), [], __FILE__, __LINE__);

    return $payload;
  }

  /**
   * Get normalized article name/slug
   * 
   * @param mixed $subject The article object or ID
   * @return string The normalized name
   */
  private static function getNormalizedName(string $value): string
  {

    return rex_string::normalize($value);
  }

  /**
   * Get normalized category name/slug
   * 
   * @param mixed $subject The category object or ID
   * @return string The normalized name
   */
  private static function getNormalizedNameById(int $id): string
  {
    $article = rex_article::get($id);
    $name = $article ? $article->getName() : 'unknown';
    return rex_string::normalize($name);
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
          self::$logger->log('info', "Webhook sent successfully (attempt {$attempt})" . self::$loggerContext . '; url: ' . $url . '; payload:' . print_r($payload, true), [], __FILE__, __LINE__);
        self::updateWebhookStatus($webhook['id'], 'success');
        return ['success' => true, 'message' => 'Webhook sent successfully'];
      }
      self::$logger->log('error', "Webhook failed (attempt {$attempt})" . self::$loggerContext . '; url: ' . $url . '; payload:' . print_r($payload, true), [], __FILE__, __LINE__);

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
        rex_response::cleanOutputBuffers();
        return true;
      }

      if ($attempt < $retryAttempts) {
        // Wait before retry (exponential backoff)
        sleep(pow(2, $attempt - 1));
      }
    }
    if (self::$isDevMode && self::$logger)
      self::$logger->log('info', "Failed to send webhook after {$retryAttempts}" . self::$loggerContext . '; url: ' . $url, [], __FILE__, __LINE__);
    rex_response::cleanOutputBuffers();

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
