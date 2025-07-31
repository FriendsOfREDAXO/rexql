<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use rex_addon;
use function rex_getUrl;

class RoutesResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_article';
    $orderBy = "CASE ";
    $orderBy .= "WHEN `startarticle` = 1 AND `parent_id` = 0 THEN `catpriority` ";
    $orderBy .= "WHEN `parent_id` != 0 THEN (
      SELECT a2.catpriority FROM `rex_article` a2 
      WHERE a2.id = `rex_article`.parent_id
    ) * 1000 + `catpriority` ";
    $orderBy .= "WHEN `startarticle` = 0 AND `parent_id` = 0 THEN 9000 + `priority` ";
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
      $this->table => ['slug', 'routeType', 'isCategory']
    ];
    $this->ensureColumns = [
      $this->table => ['status', 'catpriority']
    ];

    $this->fieldResolvers = [
      $this->table => [
        'routeType' => fn($row): string => $row['rex_article_catpriority'] !== 0 ? 'category' : 'article',
        'slug' => function ($row): string {
          $clangId = isset($row['rex_article_clang_id']) ? $row['rex_article_clang_id'] : (isset($this->args['clangId']) ? $this->args['clangId'] : 1);
          $url = rex_getUrl($row['rex_article_id'], $clangId);
          $slug = parse_url($url, PHP_URL_PATH);
          return trim($slug, '/');
        },
        'index' => fn($row): bool => ($row['rex_article_yrewrite_index'] === 0 && $row['rex_article_status']) || ($row['rex_article_yrewrite_index'] !== -1 && $row['rex_article_yrewrite_index'] !== 2),
      ]
    ];

    $results = $this->query();

    $redaxo_url = rex_addon::get('url');
    if ($redaxo_url->isAvailable()) {
      $this->table = 'rex_url_generator_url';
      $this->args['orderBy'] = "{$this->table}.`id`";
      $this->fieldsMap = [
        $this->table => [
          'parentId' => 'article_id',
          'slug' => 'url',
          'description' => 'seo',
          'image' => 'seo',
          'index' => 'sitemap',
          'name' => 'seo',
        ]
      ];
      $this->excludeFieldsFromSQL = [
        $this->table => ['status', 'startarticle', 'routeType', 'isCategory']
      ];
      $this->ensureColumns = [
        $this->table => ['data_id']
      ];
      $this->fieldResolvers = [
        $this->table => [
          'routeType' => fn(): string => 'url',
          'id' => fn($row): int => $row[$this->table . '_data_id'],
          'slug' => function ($row): string {
            $slug = parse_url($row[$this->table . '_url'], PHP_URL_PATH);
            return trim($slug, '/');
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
