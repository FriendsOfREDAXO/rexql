<?php

/**
 * rexQL Übersichtsseite
 */


use FriendsOfRedaxo\RexQL\EndpointType;
use FriendsOfRedaxo\RexQL\Utility;
use FriendsOfRedaxo\RexQL\RexQL;
use FriendsOfRedaxo\RexQL\Services\QueryLogger;


$addon = rex_addon::get('rexql');
if (!$addon->getConfig('endpoint_enabled', false)) {
  echo rex_view::warning(rex_i18n::msg('rexql_endpoint_not_enabled'));
  return;
}


/** @var RexQL $api */
$api = rex::getProperty('rexql', null);

$apiEnabled = $addon->getConfig('endpoint_enabled', false);

$content = $statusContent = '';

// Status-Übersicht

$stats = QueryLogger::getStats();
// dump($stats);
//  array:11 [▼
//     "total_queries" => 121
//     "successful_queries" => "83"
//     "failed_queries" => "38"
//     "avg_execution_time" => "25.2024793"
//     "max_execution_time" => "67.638"
//     "avg_memory_usage" => "832.0579"
//     "max_memory_usage" => 1915
//     "top_api_keys" => array:2 [▶]
//     "top_errors" => array:4 [▶]
//     "expensive_queries" => array:5 [▶]
//     "recent_queries" => array:5 [▶]
// ]
$statusContent .= '<div class="row">';

// Letzte 24 Stunden
$statusContent .= '<div class="col-sm-3">
    <h3>Letzte 24 Stunden</h3>
    <dl class="rexql-simple-table">
        <dt>Anzahl Queries:</dt><dd>' . $stats['total_queries'] . '</dd>
        <dt>Erfolgreiche:</dt><dd class="success">' . $stats['successful_queries'] . '</dd>
        <dt>Fehlgeschlagene:</dt><dd class="danger">' . $stats['failed_queries'] . '</dd>
    </dl>
</div>';

// Top API-Schlüssel
$statusContent .= '<div class="col-sm-3">
        <h3>Top 5 API-Schlüssel</h3>
        <dl class="rexql-simple-table">';
foreach ($stats['top_api_keys'] as $key) {
  $statusContent .= '<dt>' . htmlspecialchars($key['name'] ?? '[PUBLIC]') . '</dt><dd>' . $key['query_count'] . ' Queries</dd>';
}
$statusContent .= '</dl>
</div>';

// Ausführungszeit
$statusContent .= '<div class="col-sm-3">
        <h3>Ausführungszeit</h3>
    <dl class="rexql-simple-table">
        <dt>Durchschnitt:</dt><dd>' . round($stats['avg_execution_time'], 2) . ' ms</dd>
        <dt>Maximale:</dt><dd>' . round($stats['max_execution_time'], 2) . ' ms</dd>
    </dl>
</div>';

// Speicherverbrauch
$statusContent .= '<div class="col-sm-3">
        <h3>Speicherverbrauch</h3>
    <dl class="rexql-simple-table">
        <dt>Durchschnitt:</dt><dd>' . rex_formatter::bytes($stats['avg_memory_usage']) . '</dd>
        <dt>Maximal:</dt><dd>' . rex_formatter::bytes($stats['max_memory_usage']) . '</dd>
    </dl>
</div>';


$statusContent .= '</div>';
$statusContent .= '<div class="row">';

// Häufigste Fehler
$statusContent .= '<div class="col-sm-12">
        <h3>Häufigste Fehler</h3>
            <dl class="rexql-simple-table single-column">';
foreach ($stats['top_errors'] as $error) {
  $statusContent .= '<dt>' . $error['error_count'] . ' x <span class="danger">' . htmlspecialchars($error['error_message']) . '</span></dt>';
}
$statusContent .= '</dl>
</div>';
$statusContent .= '</div>';

$statusContent .= '<div class="row">';

// Letzte Abfragen
$statusContent .= '<div class="col-sm-6">
    <div>
        <h3>Letzte Abfragen</h3>';
$statusContent .= '<div class="rexql-query-list">';
foreach ($stats['recent_queries'] as $query) {
  $key = htmlspecialchars($query['name'] ?? '[PUBLIC]');
  $formattedQuery = Utility::formatGraphQLQuery($query['query'] ?? '');
  $statusContent .= '<div class="rexql-query-item">
    <pre style="margin:0"><code>' . $formattedQuery . '</code></pre>
        <small>API-Schlüssel: ' . $key . ', Datum: ' . $query['createdate'] . '</small>
        </div>';
}
$statusContent .= '</div>
    </div>
</div>';

// Teuerste Abfragen
$statusContent .= '<div class="col-sm-6">
    <div>
        <h3>Teuerste Abfragen</h3>';
$statusContent .= '<div class="rexql-query-list">';
foreach ($stats['expensive_queries'] as $query) {
  $formattedQuery = Utility::formatGraphQLQuery($query['query'] ?? '');
  $statusContent .= '<div class="rexql-query-item">
    <pre style="margin:0"><code>' . $formattedQuery . '</code></pre>
        <small>API-Schlüssel: ' . $key . ',  Ausführungszeit: ' . round($query['execution_time'], 2) . ' ms, Speicher: ' . rex_formatter::bytes($query['memory_usage']) . ', Datum: ' . $query['createdate'] . '</small>
        </div>';
}
$statusContent .= '</div>
</div>
    </div>';

$statusContent .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('rexql_stats'));
$fragment->setVar('body', $statusContent, false);
$content .= $fragment->parse('core/page/section.php');



echo $content;
