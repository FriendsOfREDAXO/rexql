<?php

/**
 * rexQL Übersichtsseite
 */


use FriendsOfRedaxo\RexQL\Utility;
use FriendsOfRedaxo\RexQL\RexQL;
use FriendsOfRedaxo\RexQL\Services\QueryLogger;


$addon = rex_addon::get('rexql');
if (!$addon->getConfig('endpoint_enabled', false)) {
    echo rex_view::warning($addon->i18n('endpoint_not_enabled'));
    return;
}


/** @var RexQL $api */
$api = rex::getProperty('rexql_api', null);

$apiEnabled = $addon->getConfig('endpoint_enabled', false);

$content = $statusContent = '';

// Status-Übersicht

$stats = QueryLogger::getStats();

$statusContent .= '<div class="row">';

// Letzte 24 Stunden
$fragmentData = [
    'name' => $addon->i18n('stats_last_24h'),
    'icon' => 'fa fa-clock primary',
    'cols' => 3,
    'data' => [
        'stats' => [
            [
                'value' => $stats['total_queries'],
                'label' => $addon->i18n('stats_total_queries'),
                'icon' => 'rex-icon fa-hashtag primary'
            ],
            [
                'value' => $stats['successful_queries'],
                'label' => $addon->i18n('stats_successful_queries'),
                'class' => 'success',
                'icon' => 'rex-icon fa-check success'
            ],
            [
                'value' => $stats['cached_queries'],
                'label' => $addon->i18n('stats_from_cache'),
                'class' => 'warning',
                'icon' => 'rex-icon fa-history warning'
            ],
            [
                'value' => $stats['failed_queries'],
                'label' => $addon->i18n('stats_failed_queries'),
                'class' => 'danger',
                'icon' => 'rex-icon fa-times danger'
            ],
        ]
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentData);

// Top API-Schlüssel
$data = [];
foreach ($stats['top_api_keys'] as $key) {
    $data[] = [
        'label' => htmlspecialchars($key['name'] ?? '[PUBLIC]'),
        'value' => $key['query_count'],
    ];
}
$fragmentdata = [
    'name' => $addon->i18n('stats_top_api_keys'),
    'icon' => 'fa fa-key primary',
    'cols' => 3,
    'data' => [
        'stats' => $data,
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentdata);

// Ausführungszeit
$fragmentData = [
    'name' => $addon->i18n('stats_execution_time'),
    'icon' => 'rex-icon fa-clock primary',
    'cols' => 3,
    'data' => [
        'stats' => [
            [
                'value' => $stats['avg_execution_time'],
                'type' => 'ms',
                'label' => $addon->i18n('stats_avg'),
            ],
            [
                'value' => $stats['max_execution_time'],
                'type' => 'ms',
                'label' => $addon->i18n('stats_max'),
            ],
        ]
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentData);


// Speicherverbrauch
$fragmentData = [
    'name' => $addon->i18n('stats_memory_usage'),
    'icon' => 'rex-icon fa-memory primary',
    'cols' => 3,
    'data' => [
        'stats' => [
            [
                'value' => $stats['avg_memory_usage'],
                'type' => 'bytes',
                'label' => $addon->i18n('stats_avg'),
                'icon' => 'rex-icon rex-icon-memory'
            ],
            [
                'value' => $stats['max_memory_usage'],
                'type' => 'bytes',
                'label' => $addon->i18n('stats_max'),
                'icon' => 'rex-icon rex-icon-memory'
            ],
        ]
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentData);

$statusContent .= '</div>';
$statusContent .= '<div class="row">';

// Häufigste Fehler
$data = [];
foreach ($stats['top_errors'] as $error) {
    $data[] = [
        'label' => $error['error_count'] . ' x <span class="danger">' . htmlspecialchars($error['error_message']) . '</span>',
    ];
}
$fragmentData = [
    'name' => $addon->i18n('stats_top_errors'),
    'icon' => 'fa fa-exclamation-triangle danger',
    'cols' => 12,
    'data' => [
        'class' => 'single-column',
        'stats' => $data,
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentData);

$statusContent .= '</div>';

$statusContent .= '<div class="row">';

// Letzte Abfragen
$fragmentData = [
    'name' => html_entity_decode($addon->i18n('stats_recent_queries'), ENT_QUOTES),
    'icon' => 'fa fa-history success',
    'cols' => 6,
    'data' => [
        'class' => 'single-column',
        'queries' => $stats['recent_queries'],
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentData);

// Teuerste Abfragen
$fragmentData = [
    'name' => html_entity_decode($addon->i18n('stats_expensive_queries'), ENT_QUOTES),
    'icon' => 'fa fa-money success',
    'cols' => 6,
    'data' => [
        'class' => 'single-column',
        'queries' => $stats['expensive_queries'],
    ]
];
$statusContent .= Utility::getFragment('stats.data', $fragmentData);

$statusContent .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('rexql_stats'));
$fragment->setVar('body', $statusContent, false);
$content .= $fragment->parse('core/page/section.php');



echo $content;
