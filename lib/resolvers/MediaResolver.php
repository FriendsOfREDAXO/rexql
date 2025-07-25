<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class MediaResolver extends ResolverBase
{
  public function getData(): array
  {
    $this->table = 'rex_media';
    $this->args['orderBy'] = 'id';

    $this->fieldsMap = [
      $this->table => [
        'name' => 'title',
        'focus' => 'med_focuspoint',
      ]
    ];

    $this->relations = [
      'rex_media_category' =>
      [
        'alias' => 'category',
        'type' => 'hasOne',
        'localKey' => 'category_id',
        'foreignKey' => 'id',
      ]
    ];

    $results = $this->query();



    return $this->typeName === 'medias' ? $results : $results[0];
  }
}
