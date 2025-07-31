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
    self::$resolvers = rex_extension::registerPoint(new rex_extension_point('REXQL_EXTEND_FIELD_RESOLVERS', self::$resolvers));
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
}
