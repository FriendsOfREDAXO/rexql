<?php

namespace FriendsOfRedaxo\RexQL\Resolver;


use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class DefaultResolver extends BaseResolver
{
  public function resolve(): \Closure
  {
    return function ($root, $args, $context, ResolveInfo $info): array {

      $typeName = $info->fieldName; // e.g. "articles"
      $type = $info->schema->getType($typeName);

      $fields = $info->getFieldSelection(1);
      $fieldKeys = array_keys($fields);
      $data = [];
      foreach ($fieldKeys as $key => $field) {

        if ($type instanceof ObjectType) {
          $fieldType = $type->getField($field)->getType();
          $namedType = Type::getNamedType($fieldType);
          $data[$field] = $namedType->name;
        } else {
          $data[$field] = '';
        }
      }
      return $data;
    };
  }
}
