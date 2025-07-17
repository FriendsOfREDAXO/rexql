<?php

namespace FriendsOfRedaxo\RexQL;

use Exception;
use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\Cache;
use FriendsOfRedaxo\RexQL\Context;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Resolver\FieldResolvers;
use FriendsOfRedaxo\RexQL\Resolver\RootResolvers;
use FriendsOfRedaxo\RexQL\Utility;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use rex_addon;
use rex_api_exception;
use rex_dir;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_i18n;

class Api
{
  protected rex_addon $addon;
  protected ApiKey|null $apiKey = null;
  protected ?int $apiKeyId = null;
  protected ?Context $context = null;

  protected bool $debugMode = false;
  protected static $fieldResolvers, $typeResolvers;
  protected static array $rootResolvers = [];

  protected Schema $schema;


  public function __construct(rex_addon $addon, bool $debugMode = false)
  {
    $this->addon = $addon;
    $this->debugMode = $debugMode;


    self::$fieldResolvers = (new FieldResolvers())->get();
    self::$rootResolvers = (new RootResolvers())->get();

    $this->checkConfig();

    $this->context = new Context();
    $this->context->set('debugMode', $debugMode);
    $this->context->set('apiKey', $this->apiKey ?? null);
    $this->context->set('cachePath', $addon->getCachePath());
    $this->context->set('cache', true);

    $this->schema = $this->generateSchema();
  }

  protected function checkConfig(): void
  {
    // Check configuration
    if (!$this->addon->getConfig('endpoint_enabled', false)) {
      throw new rex_api_exception(rex_i18n::msg('rexql_error_endpoint_disabled'));
    }

    // Check authentication
    if (Utility::isAuthEnabled()) {
      $this->apiKey = $this->validateAuthentication();
      $this->apiKeyId = $this->apiKey ? $this->apiKey->getId() : -1;

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
    } else {
      // Dev mode: Log for transparency
      if ($this->debugMode)
        Logger::log('rexQL: API access unrestricted');
    }
  }


  public function generateSchema(): Schema
  {

    $schemaCache = new Cache($this->context, 'schema');
    $schemaFilepath = $this->addon->getDataPath('schema.graphql');
    $schemaGeneratedFilepath = $this->addon->getCachePath('generated.schema.graphql');

    $sdl = self::loadSdlFile($schemaFilepath);
    if (!$sdl) {
      throw new rex_api_exception('rexQL: Generator: Schema: Schema file not found!');
    }
    $sdl = $this->handleExtensions($sdl, $schemaGeneratedFilepath);
    $generatedSdl = @self::loadSdlFile($schemaGeneratedFilepath);

    $schemaCache->setCacheKey(serialize('graphql_ast_' . $schemaFilepath . ($generatedSdl ? $generatedSdl : $sdl)));
    $cachedDoc = $schemaCache->get('graphql_ast', null);

    if ($cachedDoc) {
      $doc = AST::fromArray($cachedDoc);
      return (new BuildSchema($doc, self::$typeResolvers, [], self::$fieldResolvers))->buildSchema();
      // return (new BuildSchema($doc, self::$typeResolvers, [], self::$fieldResolvers))->buildSchema();
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
    if (isset($extensions['sdl']) && is_string($extensions['sdl']) && !empty($extensions['sdl'])) {
      $sdl .= "\n" . $extensions['sdl'];
    }
    if (isset($extensions['rootResolvers']) && is_array($extensions['rootResolvers']) && !empty($extensions['rootResolvers'])) {
      foreach ($extensions['rootResolvers'] as $type => $resolvers) {
        if (!isset(self::$rootResolvers[$type])) {
          self::$rootResolvers[$type] = [];
        }
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
      rex_request('api_key', 'string') ?: ($_SERVER['HTTP_X_API_KEY'] ?? '') ?: (isset($_SERVER['HTTP_AUTHORIZATION']) ? str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']) : '');

    if (empty($apiKeyValue)) {
      if ($this->debugMode) {
        Logger::log('rexQL: API: No API key provided');
      } else {
        throw new rex_api_exception('API-Schlüssel erforderlich');
      }
    }

    $apiKey = ApiKey::findByKey($apiKeyValue);
    if (!$apiKey && !$this->debugMode) {
      throw new rex_api_exception(rex_i18n::msg('rexql_error_invalid_api_key'));
    }

    return $apiKey;
  }

  protected function validateDomainRestrictions(?ApiKey $apiKey): bool
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

  public static function loadSdlFile($filepath): string
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
