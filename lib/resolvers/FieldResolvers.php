<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use Closure;
use GraphQL\Executor\Executor;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\FieldType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Executor\FieldResolver;
use rex_extension;
use rex_extension_point;

/**
 * @see Executor
 * 
 * @phpstan-import-type FieldResolver from Executor
 * @phpstan-import-type ArgsMapper from Executor
 * @phpstan-import-type ArgumentListConfig from Argument
 * 
 * @phpstan-type FieldType (Type&OutputType)|callable(): (Type&OutputType)
 * @phpstan-type ComplexityFn callable(int, array<string, mixed>): int
 * @phpstan-type VisibilityFn callable(): bool
 * @phpstan-type FieldDefinitionConfig array{
 *     name: string,
 *     type: FieldType,
 *     resolve?: FieldResolver|null,
 *     args?: ArgumentListConfig|null,
 *     argsMapper?: ArgsMapper|null,
 *     description?: string|null,
 *     visible?: VisibilityFn|bool,
 *     deprecationReason?: string|null,
 *     astNode?: FieldDefinitionNode|null,
 *     complexity?: ComplexityFn|null
 * }
 */
class FieldResolvers
{
  private static array $resolvers = [];

  public function __construct()
  {
    $this->registerResolvers();
  }

  protected function registerResolvers(): void
  {
    self::$resolvers = [];
    self::$resolvers = rex_extension::registerPoint(new rex_extension_point('REXQL_EXTEND_FIELD_RESOLVERS', self::$resolvers));
  }

  public function get(): Closure
  {
    /** @param FieldDefinitionConfig $config */
    return function (array $config): array {
      /** @var ?FieldDefinitionNode $astNode */
      $astNode = $config['astNode'] ?? null;
      $fieldName = $astNode->name->value ?? null;

      if (!$fieldName) {
        return $config;
      }

      if (isset(self::$resolvers[$fieldName])) {
        $config['resolve'] = self::$resolvers[$fieldName];
      }

      return $config;
    };
  }
}
