<?php

/**
 * GraphQL API Endpoint für rexQL
 */
class rex_api_rexql_graphql extends rex_api_function
{
  /**
   * API ist öffentlich zugänglich (Frontend)
   */
  protected $published = true;

  /**
   * GraphQL Query ausführen
   */
  public function execute()
  {
    // Prevent normal REDAXO response cycle from executing
    rex_response::cleanOutputBuffers();

    $addon = rex_addon::get('rexql');

    // Set CORS headers
    $this->setCorsHeaders($addon);

    // Handle preflight request (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendContent('', 'text/plain');
      return new rex_api_result(true);
    }

    // Check for schema introspection request
    if (rex_request('schema', 'bool', false) || isset($_GET['schema']) || isset($_POST['schema'])) {
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

      // Check dev mode (fewer restrictions in development)
      $isDevMode = $this->isDevMode();

      // Check authentication
      if ($addon->getConfig('require_authentication', true) && !$isDevMode) {
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
      } elseif ($isDevMode) {
        // Dev mode: Log for transparency
        rex_logger::factory()->debug('rexQL: API access in dev mode without authentication', []);
      }

      // Parse GraphQL input
      $input = $this->getGraphQLInput();
      $query = $input['query'] ?? '';
      $variables = $input['variables'] ?? null;
      $operationName = $input['operationName'] ?? null;

      if (empty($query)) {
        throw new rex_api_exception('No GraphQL query provided');
      }

      // Check query syntax in advance
      try {
        \GraphQL\Language\Parser::parse($query);
      } catch (\GraphQL\Error\SyntaxError $e) {
        throw new rex_api_exception('GraphQL Syntax Error: ' . $e->getMessage());
      }

      // Check query depth
      $maxDepth = $addon->getConfig('max_query_depth', 10);
      if ($this->getQueryDepth($query) > $maxDepth) {
        throw new rex_api_exception(rex_i18n::msg('rexql_error_query_too_deep'));
      }

      // Schema erstellen
      $schema = FriendsOfRedaxo\RexQL\Cache::getSchema(function () {
        $builder = new FriendsOfRedaxo\RexQL\SchemaBuilder();
        return $builder->buildSchema();
      });

      // Sicherstellen, dass Schema ein GraphQL\Type\Schema Objekt ist
      if (!($schema instanceof \GraphQL\Type\Schema)) {
        // Wenn Schema ein Array ist, neu erstellen
        $builder = new FriendsOfRedaxo\RexQL\SchemaBuilder();
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
        'schema_version' => FriendsOfRedaxo\RexQL\Cache::getSchemaVersion(),
        'api_key_id' => $apiKey ? $apiKey->getId() : null
      ]));

      // Check if cache should be bypassed via request parameter
      $bypassCache = rex_get('noCache', 'bool', false);

      // Custom error handling combined with caching
      $result = FriendsOfRedaxo\RexQL\Cache::getQueryResult($queryHash, function () use ($schema, $query, $context, $variables, $operationName) {
        try {
          // Validate GraphQL query
          $document = \GraphQL\Language\Parser::parse($query);
          $validationErrors = \GraphQL\Validator\DocumentValidator::validate($schema, $document);
          if (!empty($validationErrors)) {
            // Return validation errors in GraphQL result format
            return new \GraphQL\Executor\ExecutionResult(null, $validationErrors);
          } else {
            $result = \GraphQL\GraphQL::executeQuery(
              $schema,
              $query,
              null,
              $context,
              $variables,
              $operationName
            );

            // Determine if this result should be cached based on its error content
            $result->extensions['shouldCache'] = FriendsOfRedaxo\RexQL\Cache::shouldCacheResult($result);

            return $result;
          }
        } catch (\GraphQL\Error\SyntaxError $e) {
          // Syntax errors in GraphQL query (these can be cached)
          return new \GraphQL\Executor\ExecutionResult(null, [$e]);
        } catch (\Exception $e) {
          // Other execution errors (these should generally not be cached)
          $result = new \GraphQL\Executor\ExecutionResult(null, [new \GraphQL\Error\Error($e->getMessage())]);
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

      // Query protokollieren
      FriendsOfRedaxo\RexQL\QueryLogger::log(
        $apiKeyId,
        $query,
        $variables,
        $executionTime,
        $memoryUsage,
        empty($result->errors),
        $errorString
      );

      // Response aufbereiten - Original errors von GraphQL nutzen
      $response = $result->toArray();

      // Add debug information if enabled
      if ($addon->getConfig('debug_mode', false)) {
        // Preserve any existing extensions from the result
        $extensions = $response['extensions'] ?? [];

        $extensions = array_merge($extensions, [
          'executionTime' => round($executionTime, 2) . 'ms',
          'memoryUsage' => $this->formatBytes($memoryUsage),
          'queryDepth' => $this->getQueryDepth($query),
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
    } catch (Exception $e) {
      $executionTime = (microtime(true) - $startTime) * 1000;
      $memoryUsage = memory_get_usage(true) - $startMemory;

      // Fehler protokollieren
      FriendsOfRedaxo\RexQL\QueryLogger::log(
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
      exit; // Verhindern weiterer Template-Verarbeitung
    }

    // Sollte nie erreicht werden, da wir immer JSON senden
    return new rex_api_result(true);
  }

  /**
   * Authentifizierung validieren
   */
  private function validateAuthentication(): FriendsOfRedaxo\RexQL\ApiKey
  {
    // API Key aus verschiedenen Quellen versuchen
    $apiKeyValue =
      rex_request('api_key', 'string') ?: ($_SERVER['HTTP_X_API_KEY'] ?? '') ?: (isset($_SERVER['HTTP_AUTHORIZATION']) ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']) : '');

    if (empty($apiKeyValue)) {
      throw new rex_api_exception('API-Schlüssel erforderlich');
    }

    $apiKey = FriendsOfRedaxo\RexQL\ApiKey::findByKey($apiKeyValue);
    if (!$apiKey) {
      throw new rex_api_exception(rex_i18n::msg('rexql_error_invalid_api_key'));
    }

    return $apiKey;
  }

  /**
   * GraphQL Input aus Request parsen
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
   * Calculate query depth (simplified)
   */
  private function getQueryDepth(string $query): int
  {
    // Simplified calculation by counting curly braces
    $openBraces = substr_count($query, '{');
    $closeBraces = substr_count($query, '}');

    return min($openBraces, $closeBraces);
  }

  /**
   * Bytes formatieren
   */
  private function formatBytes(int $bytes): string
  {
    if ($bytes <= 0) {
      return "0.00 B";
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor(log($bytes, 1024));
    $factor = min($factor, count($units) - 1); // Prevent index out of bounds

    $formatted = number_format($bytes / pow(1024, $factor), 2, '.', '');
    return $formatted . ' ' . $units[$factor];
  }

  /**
   * CORS Headers setzen
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
   * Dev-Modus prüfen (verwendet project addon falls verfügbar)
   */
  private function isDevMode(): bool
  {
    // Check project addon's isSecure method if available
    if (rex_addon::get('project')->isAvailable()) {
      $projectClasses = get_declared_classes();
      foreach ($projectClasses as $class) {
        if (str_contains($class, 'project') && method_exists($class, 'isSecure')) {
          return !$class::isSecure();
        }
      }
    }

    // Fallback: Check for local development environment
    $devIndicators = [
      rex_server('SERVER_NAME') === 'localhost',
      str_contains(rex_server('HTTP_HOST', ''), 'localhost'),
      str_contains(rex_server('HTTP_HOST', ''), '.local'),
      str_contains(rex_server('HTTP_HOST', ''), '.test'),
      str_contains(rex_server('HTTP_HOST', ''), '127.0.0.1'),
      rex_server('SERVER_NAME') === 'dev.local'
    ];

    return in_array(true, $devIndicators, true);
  }

  /**
   * Validate domain restrictions
   */
  private function validateDomainRestrictions(?FriendsOfRedaxo\RexQL\ApiKey $apiKey): bool
  {
    if (!$apiKey) {
      return true; // No API key = no restrictions
    }

    // Domain-Validierung
    if (!FriendsOfRedaxo\RexQL\Utility::validateDomainRestrictions($apiKey)) {
      return false;
    }

    // IP-Validierung
    if (!FriendsOfRedaxo\RexQL\Utility::validateIpRestrictions($apiKey)) {
      return false;
    }

    // HTTPS-Validierung
    if (!FriendsOfRedaxo\RexQL\Utility::validateHttpsRestrictions($apiKey)) {
      return false;
    }

    return true;
  }

  /**
   * CSRF-Schutz nicht erforderlich für API
   */
  protected function requiresCsrfProtection()
  {
    return false;
  }

  /**
   * Handle schema introspection request
   */
  private function handleSchemaIntrospection(rex_addon $addon): rex_api_result
  {
    try {
      // Check dev mode (fewer restrictions in development)
      $isDevMode = $this->isDevMode();

      // Check authentication (same logic as main endpoint)
      $apiKey = null;
      if ($addon->getConfig('require_authentication', true) && !$isDevMode) {
        $apiKey = $this->validateAuthentication();

        // Check domain/IP restrictions (except in dev mode)
        if (!$this->validateDomainRestrictions($apiKey)) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_domain_not_allowed'));
        }
      } elseif ($isDevMode) {
        rex_logger::factory()->debug('rexQL: Schema introspection access in dev mode without authentication', []);
      }

      // Schema erstellen
      $schema = FriendsOfRedaxo\RexQL\Cache::getSchema(function () {
        $builder = new FriendsOfRedaxo\RexQL\SchemaBuilder();
        return $builder->buildSchema();
      });

      // Make sure schema is a GraphQL\Type\Schema object
      if (!($schema instanceof \GraphQL\Type\Schema)) {
        $builder = new FriendsOfRedaxo\RexQL\SchemaBuilder();
        $schema = $builder->buildSchema();
      }

      // Execute standard GraphQL introspection query
      $introspectionQuery = \GraphQL\Type\Introspection::getIntrospectionQuery();

      $result = \GraphQL\GraphQL::executeQuery(
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

      // JSON Response senden
      rex_response::cleanOutputBuffers();
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendJson($response);
      exit;
    } catch (Exception $e) {
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
