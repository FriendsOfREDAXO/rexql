<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class SlicesResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_article_slice';

    $this->relations = [
      'rex_module' => [
        'alias' => 'module',
        'type' => 'hasOne',
        'localKey' => 'module_id',
        'foreignKey' => 'id',
      ]
    ];

    $results = $this->query();

    return $this->typeName === 'slices' ? $results : $results[0] ?? null;
  }
}
