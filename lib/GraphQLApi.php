<?php

namespace FriendsOfRedaxo\RexQL;

use Closure;
use Exception;
use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\Cache;
use FriendsOfRedaxo\RexQL\Context;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Resolver\FieldResolvers;
use FriendsOfRedaxo\RexQL\Resolver\TypeResolvers;
use FriendsOfRedaxo\RexQL\Resolver\RootResolvers;
use FriendsOfRedaxo\RexQL\Utility;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;

use rex;
use rex_addon;
use rex_api_exception;
use rex_backend_login;
use rex_dir;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_i18n;
use rex_request;

class RexQL
{
  protected rex_addon $addon;
  protected ApiKey|null $apiKey = null;
  protected ?int $apiKeyId = null;
  protected ?Context $context = null;

  protected bool $debugMode = false;
  protected static Closure $fieldResolvers, $typeResolvers;
  /** @var array<string> */
  protected array $filteredQueryTypes = [];
  /** @var array<string> */
  protected array $queryTypes = [];
  /** @var array<string, array<string, mixed>> */
  protected static array $rootResolvers = [];
  /** @var array<string, mixed> */
  protected array $serverVars = [];
  protected Schema $schema;

  public function __construct(bool $skipConfigCheck = false)
  {

    $this->serverVars['HTTP_X_API_KEY'] = rex_request::server('HTTP_X_API_KEY', 'string', '');
    $this->serverVars['HTTP_AUTHORIZATION'] = rex_request::server('HTTP_AUTHORIZATION', 'string', '');

    /** @var rex_addon $addon */
    $addon = rex::getProperty('rexql_addon', null);

    $this->addon = $addon;
    $this->debugMode = $this->addon->getConfig('debug_mode', false);

    self::$fieldResolvers = (new FieldResolvers())->get();
    self::$typeResolvers = (new TypeResolvers())->get();
    self::$rootResolvers = (new RootResolvers())->get();

    $configCheckPassed = false;
    if (!$skipConfigCheck) {
      $configCheckPassed = $this->checkConfig();
    }

    $this->context = new Context();
    $this->context->setAddon($this->addon);
    $this->context->set('debugMode', $this->debugMode);
    $this->context->set('configCheckPassed', $configCheckPassed);
    $this->context->setApiKey($this->apiKey ?? null);
    $this->context->set('cachePath', $addon->getCachePath());
    $this->context->set('cache', $this->addon->getConfig('cache_enabled', true));

    $this->schema = $this->generateSchema();
  }

  protected function checkConfig(): bool
  {
    // Check configuration
    if (!$this->addon->getConfig('endpoint_enabled', false)) {
      throw new rex_api_exception(rex_i18n::msg('rexql_error_endpoint_disabled'));
    }

    // Check authentication
    $authEnabled = Utility::isAuthEnabled();
    if ($authEnabled) {
      $this->apiKey = $this->validateAuthentication();
      $this->apiKeyId = $this->apiKey ? $this->apiKey->getId() : -1;
      if (!$this->apiKey && !$this->debugMode) {
        throw new rex_api_exception(rex_i18n::msg('rexql_error_invalid_api_key'));
      }

      // Check domain/IP restrictions (except in dev mode)
      if (!$this->validateDomainRestrictions($this->apiKey)) {
        throw new rex_api_exception(rex_i18n::msg('rexql_error_domain_not_allowed'));
      }

      // Check rate limiting
      if ($this->apiKey) {
        if ($this->apiKey->isRateLimitedExceeded()) {
          throw new rex_api_exception(rex_i18n::msg('rexql_error_rate_limit_exceeded'));
        }
        $this->apiKey->logUsage();
      }
      return true;
    } else if (rex_backend_login::hasSession()) {
      if ($this->debugMode) {
        Logger::log('rexQL: API access in unrestricted development mode');
      }
      return true; // Development mode, no authentication required
    }
    return false;
  }


  protected function generateSchema(): Schema
  {

    $schemaCache = new Cache($this->context, 'schema');
    $schemaFilepath = $this->addon->getDataPath('schema.graphql');
    $schemaGeneratedFilepath = $this->addon->getCachePath('generated.schema.graphql');

    $sdl = self::loadSdlFile($schemaFilepath);
    if (!$sdl) {
      rex_dir::copy($this->addon->getPath('data'), $this->addon->getDataPath());
      $sdl = self::loadSdlFile($schemaFilepath);
      if (!$sdl) {
        throw new rex_api_exception('rexQL: Generator: Schema: Could not load SDL file from ' . $schemaFilepath);
      }
    }
    $sdl = $this->handleExtensions($sdl);
    $generatedSdl = self::loadSdlFile($schemaGeneratedFilepath);

    $schemaCache->setCacheKey(serialize('graphql_ast_' . $schemaFilepath . $generatedSdl . $sdl));
    $cachedDoc = $schemaCache->get('graphql_ast', null);

    if ($cachedDoc) {
      $doc = AST::fromArray($cachedDoc);

      if (!$doc instanceof DocumentNode) {
        throw new rex_api_exception('rexQL: Generator: Schema: Invalid cached document type!');
      }
      return (new BuildSchema($doc, self::$typeResolvers, [], self::$fieldResolvers))->buildSchema();
    } else {
      rex_dir::delete($this->context->get('cachePath') . 'schema', false);
    }
    rex_file::put($schemaGeneratedFilepath, $sdl);
    $sdl = self::loadSdlFile($schemaGeneratedFilepath);

    $doc = Parser::parse($sdl);
    DocumentValidator::assertValidSDL($doc);
    $schemaCache->set('graphql_ast', AST::toArray($doc));

    return (new BuildSchema($doc, self::$typeResolvers, [], self::$fieldResolvers))->buildSchema();
  }

  protected function handleExtensions(mixed $sdl): mixed
  {
    $extensions = [
      'sdl' => '',
      'rootResolvers' => [],
    ];

    $extensions = rex_extension::registerPoint(new rex_extension_point('REXQL_EXTEND', $extensions, [
      'context' => $this->context,
      'addon' => $this->addon,
    ]));

    // @phpstan-ignore isset.offset, booleanAnd.rightAlwaysTrue
    if (isset($extensions['sdl']) && is_string($extensions['sdl']) && !empty($extensions['sdl'])) {
      $sdl .= "\n" . $extensions['sdl'];
    }
    // @phpstan-ignore empty.offset, isset.offset, booleanAnd.rightAlwaysTrue, booleanAnd.alwaysFalse
    if (isset($extensions['rootResolvers']) && is_array($extensions['rootResolvers']) && !empty($extensions['rootResolvers'])) {
      foreach ($extensions['rootResolvers'] as $type => $resolvers) {
        if (!isset(self::$rootResolvers[$type])) {
          self::$rootResolvers[$type] = [];
        }
        // @phpstan-ignore function.alreadyNarrowedType
        if (is_array($resolvers)) {
          self::$rootResolvers[$type] = array_merge(self::$rootResolvers[$type], $resolvers);
        }
      }
    }

    /*
    * Type and Field resolvers are closures, so we cannot merge them directly.
    * Instead, we will handle them separately in the resolver classes.
    */

    return $sdl;
  }

  /**
   * Execute a GraphQL query
   *
   * @api
   * @param string $query The GraphQL query string
   * @param array<string, mixed> $variables Optional variables for the query
   * @param string|null $operationName Optional operation name if the query has multiple operations
   * @return array<string, mixed> The result of the query execution
   * @throws rex_api_exception If the query is invalid or execution fails
   */
  public function executeQuery(string $query, array $variables = [], string|null $operationName = null): array
  {

    if (empty($query)) {
      throw new rex_api_exception(rex_i18n::msg('rexql_error_no_query_provided'));
    }

    $queryCache = new Cache($this->context, 'query');
    $queryCache->setCacheKey(serialize($query . serialize($variables) . $operationName . json_encode(self::$rootResolvers)));
    $cachedResults = $queryCache->get('results', null);
    if ($cachedResults) {
      Logger::log('rexQL: API: Cache hit for query');
      $cachedResults['fromCache'] = true;
      return $cachedResults;
    }

    Logger::log('rexQL: API: Executing query');

    // Check query depth
    $maxDepth = $this->addon->getConfig('max_query_depth', 10);
    $queryDepthRule = new QueryDepth($maxDepth);
    DocumentValidator::addRule($queryDepthRule);

    $queryComplexityRule = new QueryComplexity(200);
    DocumentValidator::addRule($queryComplexityRule);

    try {
      $result = GraphQL::executeQuery(
        $this->schema,
        $query,
        array_merge(self::$rootResolvers['query'], self::$rootResolvers['mutation'], self::$rootResolvers['subscription']),
        $this->context,
        $variables,
        $operationName,
      )->toArray($this->debugMode ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : DebugFlag::NONE);
      $queryCache->set('results', $result);
      $result['apiKeyId'] = $this->apiKey ? $this->apiKey->getId() : null;
      $result['fromCache'] = false;

      return $result;
    } catch (SyntaxError $e) {
      // Handle syntax errors
      throw new rex_api_exception('GraphQL Syntax Error: ' . $e->getMessage());
    } catch (Error $e) {
      // Handle other GraphQL errors
      throw new rex_api_exception('GraphQL Error: ' . $e->getMessage());
    } catch (Exception $e) {
      // Handle unexpected errors
      throw new rex_api_exception('Unexpected Error: ' . $e->getMessage());
    }
  }

  protected function validateAuthentication(): ApiKey|null
  {
    // Check if API Key is provided in request
    $apiKeyValue =
      rex_request('api_key', 'string') ?: $this->serverVars['HTTP_X_API_KEY'] ?: ($this->serverVars['HTTP_AUTHORIZATION'] ? str_replace('Bearer ', '', $this->serverVars['HTTP_AUTHORIZATION']) : '');

    if (empty($apiKeyValue)) {
      if ($this->debugMode) {
        Logger::log('rexQL: API: No API key provided');
      } else {
        throw new rex_api_exception('API-Schlüssel erforderlich');
      }
    }

    $apiKey = ApiKey::findByKey($apiKeyValue);
    if (!$apiKey) {
      return null;
    }

    return $apiKey;
  }

  protected function validateDomainRestrictions(?ApiKey $apiKey): bool
  {
    if (!$apiKey) {
      return true; // No API key = no restrictions
    }

    if (!Utility::validateDomainRestrictions($apiKey)) {
      return false;
    }

    if (!Utility::validateIpRestrictions($apiKey)) {
      return false;
    }

    if (!Utility::validateHttpsRestrictions($apiKey)) {
      return false;
    }

    return true;
  }

  /**
   * Get all query types available in the schema
   *
   * @api
   * @return array<string> List of query type names
   */
  public function getQueryTypes(): array
  {
    if (!empty($this->queryTypes)) {
      return $this->queryTypes;
    }
    $queryType = $this->schema->getQueryType();
    $this->queryTypes = $queryType ? array_keys($queryType->getFields()) : [];
    return $this->queryTypes;
  }

  /**
   * Get a filtered list of query types, excluding built-in scalar types
   * and only including custom types defined in the schema.
   * This is useful for generating API documentation or UI elements that
   * should only show custom query types.
   *
   * @api
   * @return array<string> List of query type names
   */
  public function getFilteredQueryTypes(): array
  {
    if (!empty($this->filteredQueryTypes)) {
      return $this->filteredQueryTypes;
    }
    $queryTypes = $this->getQueryTypes();
    $customTypes = $this->getCustomTypes();

    $this->filteredQueryTypes = array_values(array_filter($queryTypes, function ($type) use ($queryTypes, $customTypes) {
      $typeName = $this->context->normalizeTypeName($type);
      return in_array($typeName, $queryTypes) || in_array($typeName, $customTypes);
    }));
    return $this->filteredQueryTypes;
  }

  /**
   * Get all custom types defined in the schema
   *
   * @api
   * @return array<string> List of custom type names
   */
  public function getCustomTypes(): array
  {
    return array_values(array_filter(array_keys($this->schema->getTypeMap()), function ($typeName) {
      return !in_array($typeName, ['Query', 'String', 'Int', 'Float', 'Boolean', 'ID', '__Schema', '__Type', '__TypeKind', '__Field', '__InputValue', '__EnumValue', '__Directive', '__DirectiveLocation']);
    }));
  }

  public static function loadSdlFile(string $filepath): string
  {
    if (!file_exists($filepath)) {
      return '';
    }
    $sdl = rex_file::get($filepath);
    if (!$sdl) {
      throw new rex_api_exception('rexQL: Generator: Schema: Schema file is empty at ' . $filepath);
    }
    return $sdl;
  }
}
