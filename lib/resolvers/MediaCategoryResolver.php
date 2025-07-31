<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class MediaCategoryResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $this->table = 'rex_media_category';
    $this->args['orderBy'] = 'rex_media_category.`name`';

    $this->relations = [
      'rex_media' =>
      [
        'alias' => 'medias',
        'type' => 'hasMany',
        'localKey' => 'id',
        'foreignKey' => 'category_id',
      ],
      'rex_media_category' =>
      [
        'alias' => 'children',
        'type' => 'hasMany',
        'localKey' => 'id',
        'foreignKey' => 'parent_id',
      ]
    ];

    $results = $this->query();

    return $this->typeName === 'mediaCategories' ? $results : $results[0] ?? null;
  }
}
