<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class TemplatesResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_template';
    $this->args['orderBy'] = 'rex_template.`name`';

    $this->relations = [
      'rex_article' =>
      [
        'alias' => 'articles',
        'type' => 'hasMany',
        'localKey' => 'id',
        'foreignKey' => 'template_id',
        'relations' => [
          'rex_article_slice' => [
            'alias' => 'slices',
            'type' => 'hasMany',
            'localKey' => 'id',
            'foreignKey' => 'article_id',
            'relations' => [
              'rex_module' => [
                'alias' => 'module',
                'type' => 'hasOne',
                'localKey' => 'module_id',
                'foreignKey' => 'id',
              ]
            ]
          ]
        ]
      ],

    ];

    $results = $this->query();

    return $this->typeName === 'templates' ? $results : $results[0] ?? null;
  }
}
