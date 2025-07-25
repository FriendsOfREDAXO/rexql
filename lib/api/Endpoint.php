<?php

/**
 * GraphQL API endpoint for REDAXO CMS
 */

namespace FriendsOfRedaxo\RexQL\Api;

use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\RexQL;
use FriendsOfRedaxo\RexQL\Services\QueryLogger;

use rex_addon;
use rex_api_function;
use rex_api_exception;
use rex_api_result;
use rex_formatter;
use rex_i18n;
use rex_response;

use Exception;

use function str_contains;
use function str_replace;
use function json_decode;
use function json_last_error;

class Endpoint extends rex_api_function
{
  protected $published = true;

  protected rex_addon $addon;
  protected bool $debugMode = false;
  protected bool $isIntrospectionEnabled = false;
  protected float $startTime = 0;
  protected float $startMemory = 0;
  protected ?ApiKey $apiKey = null;
  protected ?int $apiKeyId = null;
  protected string $query = '';
  protected array $variables = [];

  public function execute()
  {

    $this->initialize();

    try {

      // Parse GraphQL input
      $this->getGraphQLInput();

      $response = ['data' => null, 'errors' => []];
      $operationName = $input['operationName'] ?? null;

      if (empty($this->query)) {
        $response['errors'][] = ['message' => rex_i18n::msg('rexql_error_no_query_provided')];
      }

      // Cast variables to correct types
      $this->variables = $this->castVariables($this->variables);

      $rexql = new RexQL($this->addon, $this->debugMode);
      $response = $rexql->executeQuery($this->query, $this->variables, $operationName);

      // Send JSON response
      return $this->sendResponse($response);
    } catch (Exception $e) {

      $response = [
        'data' => null,
        'errors' => [['message' => $e->getMessage()]]
      ];

      $status = $e instanceof rex_api_exception ? rex_response::HTTP_BAD_REQUEST : rex_response::HTTP_INTERNAL_ERROR;
      return $this->sendResponse($response, $status);
    }

    // Should never reach here
    return new rex_api_result(true);
  }


  protected function initialize(): void
  {
    // Prevent normal REDAXO response cycle from executing
    rex_response::cleanOutputBuffers();

    $this->addon = rex_addon::get('rexql');
    $this->debugMode = $this->addon->getConfig('debug_mode', false);
    $this->isIntrospectionEnabled = $this->addon->getConfig('introspection_enabled', false);

    // Set CORS headers
    $this->setCorsHeaders($this->addon);
    $this->handlePreflightRequest();

    $this->startTime = microtime(true);
    $this->startMemory = memory_get_usage();

    $this->apiKey = null;
    $this->apiKeyId = null;
  }

  protected function castVariables(array $variables): array
  {
    // Cast variables to correct types
    foreach ($variables as $key => $value) {
      if (is_numeric($value)) {
        $variables[$key] = (int)$value;
      } elseif (is_string($value) && strtolower($value) === 'true') {
        $variables[$key] = true;
      } elseif (is_string($value) && strtolower($value) === 'false') {
        $variables[$key] = false;
      }
    }
    return $variables;
  }

  private function sendResponse(array $response, string $status = rex_response::HTTP_OK): void
  {
    $executionTime = $this->getExecutionTime();
    $memoryUsage = $this->getMemoryUsage();

    // Add debug information if enabled
    if ($this->debugMode) {
      $response['debug'] = array_merge($response['extensions'] ?? [], [
        'executionTime' => round($executionTime, 2) . 'ms',
        'memoryUsage' => rex_formatter::bytes($memoryUsage),
      ]);
    }

    QueryLogger::cleanup();
    $queryRaw = str_replace(["\n", "\r"], ' ', $this->query);
    QueryLogger::log(
      $this->apiKeyId,
      $queryRaw,
      $this->variables,
      $executionTime,
      $memoryUsage,
      empty($response['errors']),
      empty($response['errors']) ? null : implode(', ', array_column($response['errors'], 'message'))
    );

    rex_response::cleanOutputBuffers();
    rex_response::setStatus($status);
    rex_response::sendJson($response);
    exit;
  }

  protected function handlePreflightRequest(): ?rex_api_result
  {

    // Handle preflight request (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendContent('', 'text/plain');
      return new rex_api_result(true);
    }
    return null;
  }

  /**
   * Get GraphQL input from request
   */
  protected function getGraphQLInput(): void
  {
    // POST Request (Standard)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

      if (str_contains($contentType, 'application/json')) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new rex_api_exception('Ungültiges JSON in Request Body');
        }
        // return $input;
        $this->query = $input['query'] ?? '';
        $this->variables = $input['variables'] ?? [];
        return;
      }
    }

    // GET Request (for simple queries)
    $this->query = rex_request('query', 'string');
    $this->variables = rex_request('variables', 'string') ? json_decode(rex_post('variables', 'string'), true) : [];
  }

  /**
   * Set CORS headers based on addon configuration
   */
  protected function setCorsHeaders(rex_addon $addon): void
  {
    $allowedOrigins = $this->addon->getConfig('cors_allowed_origins', ['*']);
    $allowedMethods = $this->addon->getConfig('cors_allowed_methods', ['GET', 'POST', 'OPTIONS']);
    $allowedHeaders = $this->addon->getConfig('cors_allowed_headers', ['Content-Type', 'Authorization', 'X-API-KEY', 'X-Public-Key']);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
      rex_response::setHeader('Access-Control-Allow-Origin', in_array('*', $allowedOrigins) ? '*' : $origin);
    }

    rex_response::setHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
    rex_response::setHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
    rex_response::setHeader('Access-Control-Allow-Credentials', 'true');
    rex_response::setHeader('Access-Control-Max-Age', '86400'); // 24 Stunden
  }

  protected function getExecutionTime(): float
  {
    return (microtime(true) - $this->startTime) * 1000; // in Millisekunden
  }

  protected function getMemoryUsage(): float
  {
    return (memory_get_usage() - $this->startMemory) / 1024; // in Kilobyte
  }
}
