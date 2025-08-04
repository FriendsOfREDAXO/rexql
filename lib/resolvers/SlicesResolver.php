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
      ],
      'rex_article' => [
        'alias' => 'article',
        'type' => 'hasOne',
        'localKey' => 'article_id',
        'foreignKey' => 'id',
        'relations' => [
          'rex_template' => [
            'alias' => 'template',
            'type' => 'hasOne',
            'localKey' => 'template_id',
            'foreignKey' => 'id',
          ]
        ]
      ],
      'rex_clang' => [
        'alias' => 'language',
        'type' => 'hasOne',
        'localKey' => 'clang_id',
        'foreignKey' => 'id',
      ],
    ];

    $results = $this->query();

    return $this->typeName === 'slices' ? $results : $results[0] ?? null;
  }
}
