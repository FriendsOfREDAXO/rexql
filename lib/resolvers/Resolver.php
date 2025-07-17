<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use FriendsOfRedaxo\RexQL\Resolver\Interfaces\DeferredResolverInterface;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Resolver\DefaultResolver;
use FriendsOfRedaxo\RexQL\Resolver\ArticlesResolver;

use GraphQL\Deferred;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use GraphQL\Type\Definition\ResolveInfo;

use rex_addon;

use function ucfirst;
use function lcfirst;

class Resolver
{
  private static array $resolvers = [];
  private static array $buffer = [];

  protected bool $debugMode = false;
  protected array $allowed_tables = [];

  protected array $classMap = [
    'article' => ArticlesResolver::class,
    'articles' => ArticlesResolver::class,
  ];

  public function __construct(Schema $schema, rex_addon $addon, $debugMode)
  {

    $this->debugMode = $debugMode;
    // $allowedTables = $addon->getConfig('allowed_tables', []);
    // foreach ($allowedTables as $table) {
    //   $this->allowed_tables[] = $this->getTypeName($table);
    //   $this->allowed_tables[] = $this->getTypeName($table) . 's'; // Support plural forms
    // }

    $queryType = $schema->getQueryType();
    if ($queryType) {
      $queries = array_keys($queryType->getFields());
      foreach ($queries as $queryType) {
        if (isset(self::$resolvers[$queryType])) {
          Logger::log('rexQL: Resolver: Resolver for ' . $queryType . ' already exists. Skipping.');
          continue; // Resolver already exists
        }
        if (isset($this->classMap[$queryType])) {
          $resolverClass = $this->classMap[$queryType];
          Logger::log('rexQL: Resolver: Using custom resolver for ' . $queryType . ': ' . $resolverClass);
          self::$resolvers[$queryType] = (new $resolverClass())->resolve();
          continue;
        }
        Logger::log('rexQL: Resolver: Using default resolver for ' . $queryType);
        self::$resolvers[$queryType] = (new DefaultResolver())->resolve();
      }
    }
    // $this->deferredResolver(new Authors());
  }

  public function getResolvers(): array
  {
    return self::$resolvers;
  }

  private function getTypeName(string $table): string
  {
    // Fallback for other tables
    $parts = explode('_', $table);
    $name = '';
    foreach ($parts as $part) {
      $name .= ucfirst($part);
    }
    return lcfirst($name);
  }
}
