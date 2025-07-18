<?php

/**
 * GraphQL API endpoint for REDAXO CMS
 */

namespace FriendsOfRedaxo\RexQL\Api;

use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\RexQL;

use rex_addon;
use rex_api_function;
use rex_api_exception;
use rex_api_result;
use rex_formatter;
use rex_i18n;
use rex_logger;
use rex_response;

use Exception;
use function str_contains;

class Endpoint extends rex_api_function
{
  protected $published = true;

  protected rex_addon $addon;
  protected bool $debugMode = false;
  protected bool $isIntrospectionEnabled = false;
  protected float $startTime = 0;
  protected int $startMemory = 0;
  protected ?ApiKey $apiKey = null;
  protected ?int $apiKeyId = null;

  public function execute()
  {

    $this->initialize();

    try {

      // Parse GraphQL input
      $response = ['data' => null, 'errors' => []];
      $input = $this->getGraphQLInput();
      $query = $input['query'] ?? '';
      $variables = $input['variables'] ?? [];
      $operationName = $input['operationName'] ?? null;

      if (empty($query)) {
        $response['errors'][] = ['message' => rex_i18n::msg('rexql_error_no_query_provided')];
      }

      // Cast variables to correct types
      $variables = $this->castVariables($variables);

      $rexql = new RexQL($this->addon, $this->debugMode);
      $response = $rexql->executeQuery($query, $variables, $operationName);

      // Check for schema introspection request
      // if ($this->isIntrospectionEnabled) {
      //   if ((rex_request('schema', 'bool', false) || isset($_GET['schema']) || isset($_POST['schema']))) {
      //     $this->handleSchemaIntrospection($this->addon);
      //   }
      //   // check if is introspection query
      //   $this->isIntrospectionQuery($query);
      // }

      // // Check query syntax in advance
      // try {
      //   Parser::parse($query);
      // } catch (SyntaxError $e) {
      //   throw new rex_api_exception('GraphQL Syntax Error: ' . $e->getMessage());
      // }

      // Create schema
      // $schema = Cache::getSchema(function () {
      //   $builder = new SchemaBuilder();
      //   return $builder->buildSchema();
      // });

      // // Ensure schema is a Schema object
      // if (!($schema instanceof Schema)) {
      //   // Wenn Schema ein Array ist, neu erstellen
      //   $builder = new SchemaBuilder();
      //   $schema = $builder->buildSchema();
      // }

      // // Create context for resolvers
      // $context = [
      //   'api_key' => $this->apiKey,
      //   'user' => rex::getUser(),
      //   'clang_id' => rex_request('clang_id', 'int', rex_clang::getCurrentId())
      // ];

      // // Execute query with caching while preserving detailed error messages
      // $queryHash = md5(json_encode([
      //   'query' => $query,
      //   'variables' => $variables,
      //   'operationName' => $operationName,
      //   'clang_id' => $context['clang_id'],
      //   'schema_version' => Cache::getSchemaVersion(),
      //   'api_key_id' => $this->apiKey ? $this->apiKey->getId() : null
      // ]));

      // // Check if cache should be bypassed via request parameter
      // $bypassCache = rex_get('noCache', 'bool', false);

      // // Custom error handling combined with caching
      // $result = Cache::getQueryResult($queryHash, function () use ($schema, $query, $context, $variables, $operationName) {
      //   try {
      //     // Validate GraphQL query
      //     $document = Parser::parse($query);
      //     $validationErrors = DocumentValidator::validate($schema, $document);
      //     if (!empty($validationErrors)) {
      //       // Return validation errors in GraphQL result format
      //       return new ExecutionResult(null, $validationErrors);
      //     } else {
      //       $result = GraphQL::executeQuery(
      //         $schema,
      //         $query,
      //         null,
      //         $context,
      //         $variables,
      //         $operationName
      //       );

      //       // Determine if this result should be cached based on its error content
      //       $result->extensions['shouldCache'] = Cache::shouldCacheResult($result);

      //       return $result;
      //     }
      //   } catch (SyntaxError $e) {
      //     // Syntax errors in GraphQL query (these can be cached)
      //     return new ExecutionResult(null, [$e]);
      //   } catch (Exception $e) {
      //     // Other execution errors (these should generally not be cached)
      //     $result = new ExecutionResult(null, [new Error($e->getMessage())]);
      //     $result->extensions['shouldCache'] = false;
      //     return $result;
      //   }
      // }, !$bypassCache);

      // // Log API key usage
      // if ($this->apiKey) {
      //   $this->apiKey->logUsage();
      // }

      // Error handling for logging
      // $errorString = null;
      // if (!empty($result->errors)) {
      //   $errorMessages = [];
      //   foreach ($result->errors as $error) {
      //     if (is_object($error) && method_exists($error, 'getMessage')) {
      //       $errorMessages[] = $error->getMessage();
      //     } elseif (is_array($error) && isset($error['message'])) {
      //       $errorMessages[] = $error['message'];
      //     } else {
      //       $errorMessages[] = (string) $error;
      //     }
      //   }
      //   $errorString = implode('; ', $errorMessages);
      // }

      // // Log the query execution
      // QueryLogger::log(
      //   $this->apiKeyId,
      //   $query,
      //   $variables,
      //   $executionTime,
      //   $memoryUsage,
      //   empty($result->errors),
      //   $errorString
      // );

      // // Prepare response using original GraphQL errors
      // $response = $result->toArray();

      // // Add debug information if enabled
      // if ($this->debugMode) {
      //   // Preserve any existing extensions from the result
      //   $extensions = $response['extensions'] ?? [];

      //   $extensions = array_merge($extensions, [
      //     'executionTime' => round($executionTime, 2) . 'ms',
      //     'memoryUsage' => rex_formatter::bytes($memoryUsage),
      //     'cacheEnabled' => $this->addon->getConfig('cache_queries', false),
      //     'bypassCache' => rex_get('noCache', 'bool', false)
      //   ]);

      //   $response['extensions'] = $extensions;
      // }

      // Send JSON response
      return $this->sendResponse($response);
    } catch (Exception $e) {

      // Log errors
      // QueryLogger::log(
      //   $this->apiKeyId,
      //   $_POST['query'] ?? $_GET['query'] ?? '',
      //   null,
      //   $executionTime,
      //   $memoryUsage,
      //   false,
      //   $e->getMessage()
      // );

      // Error Response
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
    $executionTime = (microtime(true) - $this->startTime) * 1000;
    $memoryUsage = memory_get_usage() - $this->startMemory;

    // Add debug information if enabled
    if ($this->debugMode) {
      $response['debug'] = array_merge($response['extensions'] ?? [], [
        'executionTime' => round($executionTime, 2) . 'ms',
        'memoryUsage' => rex_formatter::bytes($memoryUsage),
      ]);
    }

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
   * Check if the query is an introspection query
   */
  protected function isIntrospectionQuery(string $query): void
  {
    $query = strtolower(trim($query));
    if ($this->debugMode)
      rex_logger::factory()->log('debug', 'Check for Introspection query', [], __FILE__, __LINE__);
    if (str_contains($query, 'introspectionquery') || str_contains($query, '__schema') || str_contains($query, '__type') || str_contains($query, '__typekind') || str_contains($query, '__directive') || str_contains($query, '__field') || str_contains($query, '__inputValue') || str_contains($query, '__enumvalue') || str_contains($query, '__typeNamemetafielddef') || str_contains($query, '__typename') || str_contains($query, '__schemaintrospection') || str_contains($query, '__schemaintrospectionquery') || str_contains($query, '__schemaintrospectionquerytype') || str_contains($query, '__schemaintrospectionqueryfield') || str_contains($query, '__schemaintrospectionqueryinputvalue') || str_contains($query, '__schemaintrospectionqueryenumvalue') || str_contains($query, '__schemaintrospectionquerydirectives') || str_contains($query, '__schemaintrospectionquerydirectivetype') || str_contains($query, '__schemaintrospectionquerydirectivefield') || str_contains($query, '__schemaintrospectionquerydirectiveinputvalue') || str_contains($query, '__schemaintrospectionquerydirectiveenumvalue') || str_contains($query, '__schemaintrospectionquerydirectivekind') || str_contains($query, '__schemaintrospectionquerydirectivetypekind')) {
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
    if ($this->debugMode)
      rex_logger::factory()->log('debug', 'rexQL: Introspection query check passed', [], __FILE__, __LINE__);
  }

  /**
   * Get GraphQL input from request
   */
  protected function getGraphQLInput(): array
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
      ];
    }

    // GET Request (for simple queries)
    return [
      'query' => rex_get('query', 'string'),
      'variables' => rex_get('variables', 'string') ? json_decode(rex_get('variables', 'string'), true) : null,
    ];
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

  /**
   * Handle schema introspection request
   */
  // protected function handleSchemaIntrospection(rex_addon $addon): rex_api_result
  // {

  //   try {

  //     // Check authentication (same logic as main endpoint)
  //     $apiKey = null;
  //     if ($this->addon->getConfig('require_authentication', true)) {
  //       $apiKey = $this->validateAuthentication();

  //       // Check domain/IP restrictions (except in dev mode)
  //       if (!$this->validateDomainRestrictions($apiKey)) {
  //         throw new rex_api_exception(rex_i18n::msg('rexql_error_domain_not_allowed'));
  //       }
  //     } elseif (!Utility::isAuthEnabled()) {
  //       if ($this->debugMode)
  //         rex_logger::factory()->log('debug', 'rexQL: Schema introspection access unrestricted', [], __FILE__, __LINE__);
  //     }

  //     // Create schema using cache
  //     $schema = Cache::getSchema(function () {
  //       $builder = new SchemaBuilder();
  //       return $builder->buildSchema();
  //     });

  //     // Make sure schema is a Schema object
  //     if (!($schema instanceof Schema)) {
  //       $builder = new SchemaBuilder();
  //       $schema = $builder->buildSchema();
  //     }

  //     // Execute standard GraphQL introspection query
  //     $introspectionQuery = Introspection::getIntrospectionQuery();

  //     $result = GraphQL::executeQuery(
  //       $schema,
  //       $introspectionQuery
  //     );

  //     // Log API key usage
  //     if ($apiKey) {
  //       $apiKey->logUsage();
  //     }

  //     // Prepare response
  //     $response = $result->toArray();

  //     // Add debug information if enabled
  //     if ($this->addon->getConfig('debug_mode', false)) {
  //       $response['extensions'] = [
  //         'type' => 'schema_introspection',
  //         'timestamp' => date('c')
  //       ];
  //     }

  //     // Send JSON Response
  //     rex_response::cleanOutputBuffers();
  //     rex_response::setStatus(rex_response::HTTP_OK);
  //     rex_response::sendJson($response);
  //     exit;
  //   } catch (Exception $e) {
  //     // Error Response
  //     $response = [
  //       'data' => null,
  //       'errors' => [['message' => $e->getMessage()]]
  //     ];

  //     rex_response::cleanOutputBuffers();
  //     rex_response::setStatus(
  //       $e instanceof rex_api_exception
  //         ? rex_response::HTTP_BAD_REQUEST
  //         : rex_response::HTTP_INTERNAL_ERROR
  //     );
  //     rex_response::sendJson($response);
  //     exit;
  //   }

  //   return new rex_api_result(true);
  // }
}
