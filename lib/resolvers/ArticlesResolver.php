<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use function rex_getUrl;

class ArticlesResolver extends BaseResolver
{
    protected function getData()
    {
        $this->table = 'rex_article';

        $this->fieldResolvers = [
            $this->table => [
                'slug' => function ($row): string {
                    $clangId = isset($row['clangId']) ? $row['clangId'] : ($this->args['clangId'] ?: 1);
                    $url = rex_getUrl($row['id'], $clangId);
                    $slug = parse_url($url, PHP_URL_PATH);
                    return trim($slug, '/');
                },
            ]
        ];

        $this->relations = [
            'rex_article_slice' =>
            [
                'type' => 'hasMany',
                'localKey' => 'id',
                'foreignKey' => 'article_id',
                'relations' => [
                    'rex_module' => [
                        'type' => 'hasOne',
                        'localKey' => 'module_id',
                        'foreignKey' => 'id',
                    ]
                ]
            ],
        ];

        $results = $this->query();

        return $this->typeName === 'articles' ? $results : $results[0];
    }
}
