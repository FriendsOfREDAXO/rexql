<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use rex_addon;

class WildcardResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $sprogAddon = rex_addon::get('sprog');
    if (!$sprogAddon->isAvailable()) {
      return [];
    }

    $this->table = 'rex_sprog_wildcard';
    $this->args['orderBy'] = 'wildcard';

    $this->fieldsMap = [
      $this->table => [
        'value' => 'replace',
      ]
    ];

    $results = $this->query();

    return $this->typeName === 'wildcards' ? $results : $results[0] ?? null;
  }
}
