<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use function rex_getUrl;

class ArticleResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_article';
    $this->excludeFieldsFromSQL = [
      $this->table => ['slug']
    ];
    $this->relations = [
      'rex_article_slice' =>
      [
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
          ],
          'rex_clang' =>
          [
            'alias' => 'language',
            'type' => 'hasOne',
            'localKey' => 'clang_id',
            'foreignKey' => 'id',
          ],
        ]
      ],
      'rex_template' =>
      [
        'alias' => 'template',
        'type' => 'hasOne',
        'localKey' => 'template_id',
        'foreignKey' => 'id',
      ],
      'rex_clang' =>
      [
        'alias' => 'language',
        'type' => 'hasOne',
        'localKey' => 'clang_id',
        'foreignKey' => 'id',
      ],
    ];

    $this->fieldResolvers = [
      $this->table => [
        'slug' => function ($row): string {
          $clangId = isset($row['rex_article_clang_id']) ? $row['rex_article_clang_id'] : ($this->args['clangId'] ?: 1);
          $url = rex_getUrl($row['rex_article_id'], $clangId);
          $slug = parse_url($url, PHP_URL_PATH);
          return trim($slug, '/');
        },
      ]
    ];

    $results = $this->query();

    return $this->typeName === 'articles' ? $results : $results[0] ?? null;
  }
}
