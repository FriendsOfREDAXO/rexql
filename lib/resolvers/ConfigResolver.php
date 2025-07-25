<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class ConfigResolver extends ResolverBase
{
  public function getData(): array
  {
    $this->table = 'rex_config';
    $this->args['orderBy'] = '`key`';

    $this->mainIdColumns = [
      $this->table => 'key'
    ];

    $results = $this->query();

    return $this->typeName === 'configs' ? $results : $results[0];
  }
}
