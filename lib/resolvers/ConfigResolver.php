<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class ConfigResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_config';
    $this->args['orderBy'] = "{$this->table}.`key`";

    $this->mainIdColumns = [
      $this->table => 'key'
    ];

    $results = $this->query();
    $this->log('Fetched ' . count($results) . ' config entries');

    return $this->typeName === 'configs' ? $results : $results[0] ?? null;
  }
}
