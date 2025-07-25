<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class ModulesResolver extends ResolverBase
{
  public function getData(): array
  {
    $this->table = 'rex_module';
    $this->args['orderBy'] = 'rex_module.`name`';

    $this->relations = [
      'rex_article_slice' =>
      [
        'alias' => 'slices',
        'type' => 'hasMany',
        'localKey' => 'id',
        'foreignKey' => 'module_id',
        'relations' => [
          'rex_article' => [
            'alias' => 'article',
            'type' => 'hasOne',
            'localKey' => 'article_id',
            'foreignKey' => 'id',
          ]
        ]

      ],
    ];

    $results = $this->query();

    return $this->typeName === 'modules' ? $results : $results[0];
  }
}
