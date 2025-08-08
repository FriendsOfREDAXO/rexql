<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use rex_addon;
use function rex_getUrl;

class RoutesResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_article';
    $this->mainIdColumns = [$this->table => 'pid'];
    $orderBy = "CASE ";
    $orderBy .= "WHEN `rex_article`.`startarticle` = 1 AND `rex_article`.`parent_id` = 0 THEN `rex_article`.`catpriority` ";
    $orderBy .= "WHEN `rex_article`.`parent_id` != 0 THEN (
      SELECT a2.catpriority FROM `rex_article` a2 
      WHERE a2.id = `rex_article`.`parent_id` AND a2.clang_id = `rex_article`.`clang_id`
    ) * 1000 + `rex_article`.`catpriority` ";
    $orderBy .= "WHEN `rex_article`.`startarticle` = 0 AND `rex_article`.`parent_id` = 0 THEN 9000 + `rex_article`.`priority` ";
    $orderBy .= "ELSE 9999 END";
    $this->args['orderBy'] = $orderBy;

    $this->fieldsMap = [
      $this->table => [
        'description' => 'yrewrite_description',
        'image' => 'yrewrite_image',
        'index' => 'yrewrite_index',
      ]
    ];
    $this->excludeFieldsFromSQL = [
      $this->table => ['slug', 'routeType', 'isCategory', 'urlProfile']
    ];
    $this->ensureColumns = [
      $this->table => ['id', 'status', 'catpriority', 'clang_id']
    ];

    $this->relations = [
      $this->table => [
        'alias' => 'parent',
        'type' => 'hasOne',
        'localKey' => 'parent_id',
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

    $this->fieldResolvers = [
      $this->table => [
        'routeType' => fn($row): string => $row[$this->table . '_catpriority'] !== 0 ? 'category' : 'article',
        'slug' => function ($row): string {
          $clangId = isset($row[$this->table . '_clang_id']) ? $row[$this->table . '_clang_id'] : (isset($this->args['clangId']) ? $this->args['clangId'] : 1);
          $url = rex_getUrl($row[$this->table . '_id'], $clangId);
          $slug = parse_url($url, PHP_URL_PATH);
          return trim($slug, '/');
        },
        'urlProfile' => fn(): null => null,
        'index' => fn($row): bool => ($row[$this->table . '_yrewrite_index'] === 0 && $row[$this->table . '_status']) || ($row[$this->table . '_yrewrite_index'] !== -1 && $row[$this->table . '_yrewrite_index'] !== 2),
      ]
    ];

    $results = $this->query();

    $redaxo_url = rex_addon::get('url');
    if ($redaxo_url->isAvailable()) {
      $this->relationColumns = [];
      $this->table = 'rex_url_generator_url';
      $this->args['orderBy'] = "{$this->table}.`id`";
      $this->fieldsMap = [
        $this->table => [
          'parentId' => 'article_id',
          'slug' => 'url',
          'urlProfile' => 'profile_id',
          'description' => 'seo',
          'image' => 'seo',
          'index' => 'sitemap',
          'name' => 'seo',
        ]
      ];
      $this->relations = [
        'rex_article' => [
          'alias' => 'parent',
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
      $this->excludeFieldsFromSQL = [
        $this->table => ['status', 'startarticle', 'routeType', 'isCategory']
      ];
      $this->ensureColumns = [
        $this->table => ['data_id', 'article_id', 'clang_id', 'profile_id']
      ];
      $this->fieldResolvers = [
        $this->table => [
          'routeType' => fn(): string => 'url',
          'id' => fn($row): int => $row[$this->table . '_data_id'],
          'slug' => function ($row): string {
            $parentUrl = rex_getUrl($row[$this->table . '_article_id'], $row[$this->table . '_clang_id']);
            $baseUrl = parse_url($parentUrl, PHP_URL_PATH);
            $slug = parse_url($row[$this->table . '_url'], PHP_URL_PATH);
            return ltrim($baseUrl, '/') . trim(str_replace($baseUrl, '', $slug), '/');
          },
          'urlProfile' => function ($row): string {
            $profileId = $row[$this->table . '_profile_id'] ?? null;
            if ($profileId) {
              $profile = \Url\Profile::get($profileId);
              return $profile ? $profile->getNamespace() : '';
            }
            return '';
          },
          'name' => function ($row): string {
            $data = json_decode($row[$this->table . '_seo'], true);
            return $data['title'] ?? '';
          },
          'description' => function ($row): string {
            $data = json_decode($row[$this->table . '_seo'], true);
            return $data['description'] ?? '';
          },
          'image' => function ($row): string {
            $data = json_decode($row[$this->table . '_seo'], true);
            return $data['image'] ?? '';
          },
          // 'index' => fn($row): bool => ($row['rex_article_yrewrite_index'] === 0 && $row['rex_article_status']) || ($row['rex_article_yrewrite_index'] !== -1 && $row['rex_article_yrewrite_index'] !== 2),
        ]
      ];
      $urlResults = $this->query();
      $results = array_merge($results, $urlResults);
    }

    return $results;
  }
}
