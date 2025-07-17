<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use Closure;
use GraphQL\Language\AST\TypeDefinitionNode;
use rex_extension;
use rex_extension_point;

class TypeResolvers
{
  private static array $resolvers = [];

  public function __construct()
  {
    $this->registerResolvers();
  }

  protected function registerResolvers(): void
  {
    self::$resolvers = [];
    self::$resolvers = rex_extension::registerPoint(new rex_extension_point('REXQL_EXTEND_TYPE_RESOLVERS', self::$resolvers));
  }

  public function get(): Closure
  {
    return function (
      array $typeConfig,
      TypeDefinitionNode $typeDefinitionNode,
      $parentTypeDefinitionNode
    ): array {
      $fieldName = $typeDefinitionNode->name->value;
      $parentTypeName = $parentTypeDefinitionNode->name->value;
      $default = fn($root, $args, $context, $info) => $root[$info->fieldName] ?? null;

      if ($parentTypeName === 'Query') {
        if (isset(self::$resolvers[$fieldName])) {
          $typeConfig['resolve'] = self::$resolvers[$fieldName];
        } else {
          // Default resolver if no specific resolver is registered
          $typeConfig['resolve'] = $default;
        }
      } elseif ($parentTypeName === 'Mutation') {
        $typeConfig['resolve'] = $default;
      } else {
        $typeConfig['resolve'] = $default;
      }
      return $typeConfig;
    };
  }
}
