<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use Closure;
use rex_extension;
use rex_extension_point;

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
    self::$resolvers = rex_extension::registerPoint(new rex_extension_point('REXQL_EXTEND_TYPE_RESOLVERS', self::$resolvers));
  }

  public function get(): Closure
  {
    return function (array $config): array {
      $fieldName = $config['astNode']->name->value ?? '';
      if (!$fieldName) {
        return $config;
      }
      if (isset(self::$resolvers[$fieldName])) {
        $config['resolve'] = self::$resolvers[$fieldName];
      }

      return $config;
    };
  }

  //   return function (
  //     array $fieldConfig,
  //     FieldDefinitionNode $fieldDefinitionNode,
  //     $parentTypeDefinitionNode
  //   ): array {
  //     $fieldName = $fieldDefinitionNode->name->value;
  //     $parentTypeName = $parentTypeDefinitionNode->name->value;
  //     $default = fn($root, $args, $context, $info) => $root[$info->fieldName] ?? null;

  //     if ($parentTypeName === 'Query') {
  // if (isset(self::$resolvers[$fieldName])) {
  //   $fieldConfig['resolve'] = self::$resolvers[$fieldName];
  // } else {
  //   // Default resolver if no specific resolver is registered
  //   $fieldConfig['resolve'] = fn($root, $args, $context, $info) => $root[$info->fieldName] ?? null;
  // }
  //     } elseif ($parentTypeName === 'Mutation') {
  //       $fieldConfig['resolve'] = fn($root, $args, $context, $info) => $root[$info->fieldName] ?? null;
  //     } else {
  //       $fieldConfig['resolve'] = fn($root, $args, $context, $info) => $root[$info->fieldName] ?? null;
  //     }
  //     return $fieldConfig;
  //   };
  // }
}
