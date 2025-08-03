<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use Closure;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
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
    // Register extension point for custom type resolvers
    self::$resolvers = rex_extension::registerPoint(new rex_extension_point('REXQL_EXTEND_TYPE_RESOLVERS', self::$resolvers));
  }

  /**
   * Returns a Closure that processes type definitions and applies resolvers.
   * 
   * The GraphQL library expects a specific signature for type config decorators.
   *
   * @api
   * @return Closure
   */
  public function get(): Closure
  {
    return function (
      array $typeConfig,
      TypeDefinitionNode $typeDefinitionNode,
      array $typeDefinitionsMap
    ): array {

      $fieldName = $typeDefinitionNode->getName()->value ?? null;

      // Get parent type from the type definitions map
      $parentTypeName = $this->getParentTypeName($typeDefinitionNode, $typeDefinitionsMap);

      // Default resolver for field access
      $default = fn($root, $args, $context, ResolveInfo $info) => $root[$info->fieldName] ?? null;

      // Apply resolvers based on parent type
      if ($parentTypeName === 'Query') {
        if (isset(self::$resolvers[$fieldName])) {
          $typeConfig['resolve'] = self::$resolvers[$fieldName];
        } else {
          // Default resolver if no specific resolver is registered
          $typeConfig['resolve'] = $default;
        }
      } elseif ($parentTypeName === 'Mutation') {
        // Apply mutation resolvers when mutations are implemented
        $typeConfig['resolve'] = $default;
      } else {
        // Default resolver for all other types
        $typeConfig['resolve'] = $default;
      }

      return $typeConfig;
    };
  }

  /**
   * Extract parent type name from the context
   * 
   * @param TypeDefinitionNode $typeDefinitionNode
   * @param array $typeDefinitionsMap
   * @return string|null
   */
  private function getParentTypeName(TypeDefinitionNode $typeDefinitionNode, array $typeDefinitionsMap): ?string
  {
    // Check if this is a field definition within a parent type
    $typeName = $typeDefinitionNode->getName()->value ?? null;

    // Look for Query or Mutation types in the definitions map
    foreach ($typeDefinitionsMap as $key => $definition) {
      if ($definition instanceof TypeDefinitionNode) {
        $definitionName = $definition->getName()->value ?? null;
        if ($definitionName === 'Query' || $definitionName === 'Mutation') {
          // Check if current field belongs to this type
          if (isset($definition->fields) && is_array($definition->fields)) {
            /** @var TypeDefinitionNode $field */
            foreach ($definition->fields as $field) {
              if (isset($field->getName()->value) && $field->getName()->value === $typeName) {
                return $definitionName;
              }
            }
          }
        }
      }
    }

    return null;
  }

  /**
   * Register a custom type resolver
   * 
   * @api
   * @param string $typeName
   * @param Closure $resolver
   */
  public static function register(string $typeName, Closure $resolver): void
  {
    self::$resolvers[$typeName] = $resolver;
  }

  /**
   * Get all registered resolvers
   * 
   * @api
   * @return array
   */
  public static function getResolvers(): array
  {
    return self::$resolvers;
  }
}
