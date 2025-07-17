<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class SlicesResolver extends BaseResolver
{
  protected function getData()
  {
    $this->table = 'rex_article_slice';

    $this->relations = [
      'rex_module' => [
        'type' => 'hasOne',
        'localKey' => 'module_id',
        'foreignKey' => 'id',
      ]
    ];

    $results = $this->query();

    return $this->typeName === 'slices' ? $results : $results[0];
  }
}
