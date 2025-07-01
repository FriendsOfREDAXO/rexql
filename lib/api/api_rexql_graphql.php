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
    // Verhindern, dass der normale REDAXO Response-Zyklus ausgeführt wird
    rex_response::cleanOutputBuffers();

    $addon = rex_addon::get('rexql');

    // CORS Headers setzen
    $this->setCorsHeaders($addon);

    // Preflight Request (OPTIONS) behandeln
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendContent('', 'text/plain');
      return new rex_api_result(true);
    }

    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    $apiKey = null;
    $apiKeyId = null;

    try {
      // Konfiguration prüfen
      if (!$addon->getConfig('endpoint_enabled', false)) {
        throw new rex_api_exception(rex_i18n::msg('rexql_error_endpoint_disabled'));
      }

      // Dev-Modus prüfen (weniger Restrictions in Development)
      $isDevMode = $this->isDevMode();

      // Authentifizierung prüfen
      if ($addon->getConfig('require_authentication', true) && !$isDevMode) {
        $apiKey = $this->validateAuthentication();
        $apiKeyId = $apiKey->getId();

        // Domain/IP Restrictions prüfen (außer in Dev-Modus)
        if (!$this->validateDomainRestrictions($apiKey)) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_domain_not_allowed'));
        }

        // Rate Limiting prüfen
        if ($apiKey->isRateLimited()) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_rate_limit_exceeded'));
        }
      } elseif ($isDevMode) {
        // Dev-Modus: Log für Transparenz  
        rex_logger::factory()->debug('rexQL: API access in dev mode without authentication', []);
      }

      // GraphQL Input parsen
      $input = $this->getGraphQLInput();
      $query = $input['query'] ?? '';
      $variables = $input['variables'] ?? null;
      $operationName = $input['operationName'] ?? null;

      if (empty($query)) {
        throw new rex_api_exception('Keine GraphQL Query angegeben');
      }

      // Query-Tiefe prüfen
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

      // Kontext für Resolver erstellen
      $context = [
        'api_key' => $apiKey,
        'user' => rex::getUser(),
        'clang_id' => rex_request('clang_id', 'int', rex_clang::getCurrentId())
      ];

      // Query ausführen
      $queryHash = md5($query . serialize($variables));
      $result = FriendsOfRedaxo\RexQL\Cache::getQueryResult($queryHash, function () use ($schema, $query, $variables, $operationName, $context) {
        return \GraphQL\GraphQL::executeQuery(
          $schema,
          $query,
          null,
          $context,
          $variables,
          $operationName
        );
      });

      // API Key Usage protokollieren
      if ($apiKey) {
        $apiKey->logUsage();
      }

      // Execution Details
      $executionTime = (microtime(true) - $startTime) * 1000; // in ms
      $memoryUsage = memory_get_usage(true) - $startMemory;

      // Query protokollieren
      FriendsOfRedaxo\RexQL\QueryLogger::log(
        $apiKeyId,
        $query,
        $variables,
        $executionTime,
        $memoryUsage,
        empty($result->errors),
        !empty($result->errors) ? implode('; ', array_map(fn($e) => $e->getMessage(), $result->errors)) : null
      );

      // Response aufbereiten
      $response = [
        'errors' => $result->errors ? array_map(fn($e) => ['message' => $e->getMessage()], $result->errors) : null,
        ...$result->toArray()
      ];

      // Debug-Informationen hinzufügen wenn aktiviert
      if ($addon->getConfig('debug_mode', false)) {
        $response['extensions'] = [
          'executionTime' => round($executionTime, 2) . 'ms',
          'memoryUsage' => $this->formatBytes($memoryUsage),
          'queryDepth' => $this->getQueryDepth($query)
        ];
      }

      // JSON Response senden
      rex_response::cleanOutputBuffers();
      rex_response::setStatus(rex_response::HTTP_OK);
      rex_response::sendJson($response);
      exit; // Verhindern weiterer Template-Verarbeitung
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

    // GET Request (für einfache Queries)
    return [
      'query' => rex_get('query', 'string'),
      'variables' => rex_get('variables', 'string') ? json_decode(rex_get('variables', 'string'), true) : null,
      'operationName' => rex_get('operationName', 'string')
    ];
  }

  /**
   * Query-Tiefe berechnen (vereinfacht)
   */
  private function getQueryDepth(string $query): int
  {
    // Vereinfachte Berechnung durch Zählen der geschweiften Klammern
    $openBraces = substr_count($query, '{');
    $closeBraces = substr_count($query, '}');

    return min($openBraces, $closeBraces);
  }

  /**
   * Bytes formatieren
   */
  private function formatBytes(int $bytes): string
  {
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
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

    // Fallback: Check für lokale Development-Umgebung
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
   * Domain-Restrictions validieren
   */
  private function validateDomainRestrictions(?FriendsOfRedaxo\RexQL\ApiKey $apiKey): bool
  {
    if (!$apiKey) {
      return true; // Keine API Key = keine Restrictions
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
}
