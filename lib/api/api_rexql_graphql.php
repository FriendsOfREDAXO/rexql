<?php

/**
 * GraphQL API endpoint for REDAXO CMS
 */

namespace FriendsOfRedaxo\RexQL\Api;

use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Type\Introspection;

use FriendsOfRedaxo\RexQL\Utility;
use FriendsOfRedaxo\RexQL\Cache;
use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\SchemaBuilder;
use FriendsOfRedaxo\RexQL\QueryLogger;

use rex;
use rex_addon;
use rex_api_function;
use rex_api_exception;
use rex_clang;
use rex_api_result;
use rex_response;
use rex_i18n;
use rex_logger;
use rex_formatter;


class rex_api_rexql_graphql extends rex_api_function
{
  protected $published = true;
  private bool $debugMode = false;
  private bool $isIntrospectionEnabled = false;
  private int $queryDepth = 0;

  public function execute()
  {
    // Prevent normal REDAXO response cycle from executing
    rex_response::cleanOutputBuffers();

    $addon = rex_addon::get('rexql');
    $this->debugMode = $addon->getConfig('debug_mode', false);
    $this->isIntrospectionEnabled = $addon->getConfig('introspection_enabled', false);

    // Set CORS headers
    $this->setCorsHeaders($addon);

    // Handle preflight request (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendContent('', 'text/plain');
      return new rex_api_result(true);
    }

    // Check for schema introspection request
    if ((rex_request('schema', 'bool', false) || isset($_GET['schema']) || isset($_POST['schema'])) && $this->isIntrospectionEnabled) {
      return $this->handleSchemaIntrospection($addon);
    }

    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    $apiKey = null;
    $apiKeyId = null;

    try {
      // Check configuration
      if (!$addon->getConfig('endpoint_enabled', false)) {
        throw new rex_api_exception(rex_i18n::msg('rexql_error_endpoint_disabled'));
      }

      // Check authentication
      if (Utility::isAuthEnabled()) {
        $apiKey = $this->validateAuthentication();
        $apiKeyId = $apiKey->getId();

        // Check domain/IP restrictions (except in dev mode)
        if (!$this->validateDomainRestrictions($apiKey)) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_domain_not_allowed'));
        }

        // Check rate limiting
        if ($apiKey->isRateLimited()) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_rate_limit_exceeded'));
        }
      } else {
        // Dev mode: Log for transparency
        if ($this->debugMode)
          rex_logger::factory()->log('debug', 'rexQL: API access unrestricted', [], __FILE__, __LINE__);
      }

      // Parse GraphQL input
      $input = $this->getGraphQLInput();
      $query = $input['query'] ?? '';
      $variables = $input['variables'] ?? null;
      $operationName = $input['operationName'] ?? null;

      if (empty($query)) {
        throw new rex_api_exception('No GraphQL query provided');
      }

      // check if is introspection query
      $this->isIntrospectionQuery($query);

      // Check query syntax in advance
      try {
        Parser::parse($query);
      } catch (SyntaxError $e) {
        throw new rex_api_exception('GraphQL Syntax Error: ' . $e->getMessage());
      }

      // Check query depth
      $maxDepth = $addon->getConfig('max_query_depth', 10);
      $this->queryDepth = $this->getQueryDepth($query);
      if ($this->queryDepth > $maxDepth) {
        throw new rex_api_exception(rex_i18n::msg('rexql_error_query_too_deep'));
      }

      // Create schema
      $schema = Cache::getSchema(function () {
        $builder = new SchemaBuilder();
        return $builder->buildSchema();
      });

      // Ensure schema is a Schema object
      if (!($schema instanceof Schema)) {
        // Wenn Schema ein Array ist, neu erstellen
        $builder = new SchemaBuilder();
        $schema = $builder->buildSchema();
      }

      // Create context for resolvers
      $context = [
        'api_key' => $apiKey,
        'user' => rex::getUser(),
        'clang_id' => rex_request('clang_id', 'int', rex_clang::getCurrentId())
      ];

      // Execute query with caching while preserving detailed error messages
      $queryHash = md5(json_encode([
        'query' => $query,
        'variables' => $variables,
        'operationName' => $operationName,
        'clang_id' => $context['clang_id'],
        'schema_version' => Cache::getSchemaVersion(),
        'api_key_id' => $apiKey ? $apiKey->getId() : null
      ]));

      // Check if cache should be bypassed via request parameter
      $bypassCache = rex_get('noCache', 'bool', false);

      // Custom error handling combined with caching
      $result = Cache::getQueryResult($queryHash, function () use ($schema, $query, $context, $variables, $operationName) {
        try {
          // Validate GraphQL query
          $document = Parser::parse($query);
          $validationErrors = DocumentValidator::validate($schema, $document);
          if (!empty($validationErrors)) {
            // Return validation errors in GraphQL result format
            return new ExecutionResult(null, $validationErrors);
          } else {
            $result = GraphQL::executeQuery(
              $schema,
              $query,
              null,
              $context,
              $variables,
              $operationName
            );

            // Determine if this result should be cached based on its error content
            $result->extensions['shouldCache'] = Cache::shouldCacheResult($result);

            return $result;
          }
        } catch (SyntaxError $e) {
          // Syntax errors in GraphQL query (these can be cached)
          return new ExecutionResult(null, [$e]);
        } catch (\Exception $e) {
          // Other execution errors (these should generally not be cached)
          $result = new ExecutionResult(null, [new Error($e->getMessage())]);
          $result->extensions['shouldCache'] = false;
          return $result;
        }
      }, !$bypassCache);

      // Log API key usage
      if ($apiKey) {
        $apiKey->logUsage();
      }

      // Execution details
      $executionTime = (microtime(true) - $startTime) * 1000; // in ms
      $memoryUsage = memory_get_usage(true) - $startMemory;

      // Error handling for logging
      $errorString = null;
      if (!empty($result->errors)) {
        $errorMessages = [];
        foreach ($result->errors as $error) {
          if (is_object($error) && method_exists($error, 'getMessage')) {
            $errorMessages[] = $error->getMessage();
          } elseif (is_array($error) && isset($error['message'])) {
            $errorMessages[] = $error['message'];
          } else {
            $errorMessages[] = (string) $error;
          }
        }
        $errorString = implode('; ', $errorMessages);
      }

      // Log the query execution
      QueryLogger::log(
        $apiKeyId,
        $query,
        $variables,
        $executionTime,
        $memoryUsage,
        empty($result->errors),
        $errorString
      );

      // Prepare response using original GraphQL errors
      $response = $result->toArray();

      // Add debug information if enabled
      if ($this->debugMode) {
        // Preserve any existing extensions from the result
        $extensions = $response['extensions'] ?? [];

        if ($this->debugMode)
          rex_logger::factory()->log('debug', 'rexQL: Query depth calculated: ' . $this->queryDepth, [], __FILE__, __LINE__);

        $extensions = array_merge($extensions, [
          'executionTime' => round($executionTime, 2) . 'ms',
          'memoryUsage' => rex_formatter::bytes($memoryUsage),
          'queryDepth' => $this->queryDepth,
          'cacheEnabled' => $addon->getConfig('cache_queries', false),
          'bypassCache' => rex_get('noCache', 'bool', false)
        ]);

        $response['extensions'] = $extensions;
      }

      // Send JSON response
      rex_response::cleanOutputBuffers();
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendJson($response);
      exit; // Prevent further template processing
    } catch (\Exception $e) {
      $executionTime = (microtime(true) - $startTime) * 1000;
      $memoryUsage = memory_get_usage(true) - $startMemory;

      // Log errors
      QueryLogger::log(
        $apiKeyId,
        $_POST['query'] ?? $_GET['query'] ?? '',
        null,
        $executionTime,
        $memoryUsage,
        false,
        $e->getMessage()
      );

      // Error Response
      $response = [
        'data' => null,
        'errors' => [['message' => $e->getMessage()]]
      ];

      rex_response::cleanOutputBuffers();
      rex_response::setStatus($e instanceof rex_api_exception ? rex_response::HTTP_BAD_REQUEST : rex_response::HTTP_INTERNAL_ERROR);
      rex_response::sendJson($response);
      exit;
    }

    // Should never reach here
    return new rex_api_result(true);
  }

  /**
   * Check if the query is an introspection query
   */
  private function isIntrospectionQuery(string $query): void
  {
    $query = strtolower(trim($query));
    if ($this->debugMode)
      rex_logger::factory()->log('debug', 'Check for Introspection query', [], __FILE__, __LINE__);
    if (\str_contains($query, 'introspectionquery') || \str_contains($query, '__schema') || \str_contains($query, '__type') || \str_contains($query, '__typekind') || \str_contains($query, '__directive') || \str_contains($query, '__field') || \str_contains($query, '__inputValue') || \str_contains($query, '__enumvalue') || \str_contains($query, '__typeNamemetafielddef') || \str_contains($query, '__typename') || \str_contains($query, '__schemaintrospection') || \str_contains($query, '__schemaintrospectionquery') || \str_contains($query, '__schemaintrospectionquerytype') || \str_contains($query, '__schemaintrospectionqueryfield') || \str_contains($query, '__schemaintrospectionqueryinputvalue') || \str_contains($query, '__schemaintrospectionqueryenumvalue') || \str_contains($query, '__schemaintrospectionquerydirectives') || \str_contains($query, '__schemaintrospectionquerydirectivetype') || \str_contains($query, '__schemaintrospectionquerydirectivefield') || \str_contains($query, '__schemaintrospectionquerydirectiveinputvalue') || \str_contains($query, '__schemaintrospectionquerydirectiveenumvalue') || \str_contains($query, '__schemaintrospectionquerydirectivekind') || \str_contains($query, '__schemaintrospectionquerydirectivetypekind')) {
      if (!$this->isIntrospectionEnabled) {
        if ($this->debugMode)
          rex_logger::factory()->log('debug', 'rexQL: Introspection query detected but disabled', [], __FILE__, __LINE__);
        $response = [
          'data' => null,
          'errors' => [['message' => 'Introspection queries are disabled']]
        ];
        rex_response::cleanOutputBuffers();
        rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
        rex_response::sendJson($response);
        exit;
      }
    }
    if ($this->debugMode)
      rex_logger::factory()->log('debug', 'rexQL: Introspection query check passed', [], __FILE__, __LINE__);
  }

  /**
   * Validate authentication via API Key
   */
  private function validateAuthentication(): ApiKey
  {
    // Check if API Key is provided in request
    $apiKeyValue =
      rex_request('api_key', 'string') ?: ($_SERVER['HTTP_X_API_KEY'] ?? '') ?: (isset($_SERVER['HTTP_AUTHORIZATION']) ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']) : '');

    if (empty($apiKeyValue)) {
      throw new rex_api_exception('API-Schlüssel erforderlich');
    }

    $apiKey = ApiKey::findByKey($apiKeyValue);
    if (!$apiKey) {
      throw new rex_api_exception(rex_i18n::msg('rexql_error_invalid_api_key'));
    }

    return $apiKey;
  }

  /**
   * Get GraphQL input from request
   */
  private function getGraphQLInput(): array
  {
    // POST Request (Standard)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

      if (str_contains($contentType, 'application/json')) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new rex_api_exception('Ungültiges JSON in Request Body');
        }
        return $input;
      }

      // Form-encoded POST
      return [
        'query' => rex_post('query', 'string'),
        'variables' => rex_post('variables', 'string') ? json_decode(rex_post('variables', 'string'), true) : null,
        'operationName' => rex_post('operationName', 'string')
      ];
    }

    // GET Request (for simple queries)
    return [
      'query' => rex_get('query', 'string'),
      'variables' => rex_get('variables', 'string') ? json_decode(rex_get('variables', 'string'), true) : null,
      'operationName' => rex_get('operationName', 'string')
    ];
  }

  /**
   * Calculate query depth more accurately
   * This method parses GraphQL syntax properly, ignoring comments and strings
   */
  private function getQueryDepth(string $query): int
  {
    // Remove comments first (lines starting with #)
    $lines = explode("\n", $query);
    $cleanedLines = [];

    foreach ($lines as $line) {
      // Remove inline comments (everything after #)
      $commentPos = strpos($line, '#');
      if ($commentPos !== false) {
        $line = substr($line, 0, $commentPos);
      }
      $cleanedLines[] = $line;
    }

    $cleanedQuery = implode("\n", $cleanedLines);

    // Remove string literals to avoid counting braces inside strings
    $cleanedQuery = preg_replace('/""".*?"""/s', '""', $cleanedQuery); // Triple-quoted strings
    $cleanedQuery = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', $cleanedQuery); // Regular strings

    // Now calculate the actual nesting depth
    $depth = 0;
    $maxDepth = 0;
    $length = strlen($cleanedQuery);

    for ($i = 0; $i < $length; $i++) {
      $char = $cleanedQuery[$i];

      if ($char === '{') {
        $depth++;
        $maxDepth = max($maxDepth, $depth);
      } elseif ($char === '}') {
        $depth--;
      }
    }

    return $maxDepth;
  }

  /**
   * Set CORS headers based on addon configuration
   */
  private function setCorsHeaders(rex_addon $addon): void
  {
    $allowedOrigins = $addon->getConfig('cors_allowed_origins', ['*']);
    $allowedMethods = $addon->getConfig('cors_allowed_methods', ['GET', 'POST', 'OPTIONS']);
    $allowedHeaders = $addon->getConfig('cors_allowed_headers', ['Content-Type', 'Authorization', 'X-API-KEY', 'X-Public-Key']);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
      rex_response::setHeader('Access-Control-Allow-Origin', in_array('*', $allowedOrigins) ? '*' : $origin);
    }

    rex_response::setHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
    rex_response::setHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
    rex_response::setHeader('Access-Control-Allow-Credentials', 'true');
    rex_response::setHeader('Access-Control-Max-Age', '86400'); // 24 Stunden
  }

  /**
   * Validate domain restrictions
   */
  private function validateDomainRestrictions(?ApiKey $apiKey): bool
  {
    if (!$apiKey) {
      return true; // No API key = no restrictions
    }

    // Domain-Validierung
    if (!Utility::validateDomainRestrictions($apiKey)) {
      return false;
    }

    // IP-Validierung
    if (!Utility::validateIpRestrictions($apiKey)) {
      return false;
    }

    // HTTPS-Validierung
    if (!Utility::validateHttpsRestrictions($apiKey)) {
      return false;
    }

    return true;
  }

  /**
   * Handle schema introspection request
   */
  private function handleSchemaIntrospection(rex_addon $addon): rex_api_result
  {

    try {

      // Check authentication (same logic as main endpoint)
      $apiKey = null;
      if ($addon->getConfig('require_authentication', true)) {
        $apiKey = $this->validateAuthentication();

        // Check domain/IP restrictions (except in dev mode)
        if (!$this->validateDomainRestrictions($apiKey)) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_domain_not_allowed'));
        }
      } elseif (!Utility::isAuthEnabled()) {
        if ($this->debugMode)
          rex_logger::factory()->log('debug', 'rexQL: Schema introspection access unrestricted', [], __FILE__, __LINE__);
      }

      // Create schema using cache
      $schema = Cache::getSchema(function () {
        $builder = new SchemaBuilder();
        return $builder->buildSchema();
      });

      // Make sure schema is a Schema object
      if (!($schema instanceof Schema)) {
        $builder = new SchemaBuilder();
        $schema = $builder->buildSchema();
      }

      // Execute standard GraphQL introspection query
      $introspectionQuery = Introspection::getIntrospectionQuery();

      $result = GraphQL::executeQuery(
        $schema,
        $introspectionQuery
      );

      // Log API key usage
      if ($apiKey) {
        $apiKey->logUsage();
      }

      // Prepare response
      $response = $result->toArray();

      // Add debug information if enabled
      if ($addon->getConfig('debug_mode', false)) {
        $response['extensions'] = [
          'type' => 'schema_introspection',
          'timestamp' => date('c')
        ];
      }

      // Send JSON Response
      rex_response::cleanOutputBuffers();
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendJson($response);
      exit;
    } catch (\Exception $e) {
      // Error Response
      $response = [
        'data' => null,
        'errors' => [['message' => $e->getMessage()]]
      ];

      rex_response::cleanOutputBuffers();
      rex_response::setStatus(
        $e instanceof rex_api_exception
          ? rex_response::HTTP_BAD_REQUEST
          : rex_response::HTTP_INTERNAL_ERROR
      );
      rex_response::sendJson($response);
      exit;
    }

    return new rex_api_result(true);
  }
}
