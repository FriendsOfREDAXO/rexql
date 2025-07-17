<?php

/**
 * rexQL Übersichtsseite
 */

$addon = rex_addon::get('rexql');

$content = '';

// Status-Übersicht
$statusContent = '<div class="row">';

// API Status
$apiEnabled = $addon->getConfig('endpoint_enabled', false);
$statusContent .= '<div class="col-sm-3">
    <div class="rex-tile">
        <h5>API Status</h5>
        <p class="rex-tile-text">' .
    ($apiEnabled ? '<span class="rex-online">Aktiv</span>' : '<span class="rex-offline">Inaktiv</span>') .
    '</p>
    </div>
</div>';

// Anzahl API-Schlüssel
$sql = rex_sql::factory();
$sql->setQuery('SELECT COUNT(*) as count FROM ' . rex::getTable('rexql_api_keys') . ' WHERE active = 1');
$apiKeyCount = $sql->getValue('count');

$statusContent .= '<div class="col-sm-3">
    <div class="rex-tile">
        <h5>API-Schlüssel</h5>
        <p class="rex-tile-text">' . $apiKeyCount . '</p>
    </div>
</div>';

// Verfügbare Tabellen
$allowedTables = count($addon->getConfig('allowed_tables', []));
$statusContent .= '<div class="col-sm-3">
    <div class="rex-tile">
        <h5>Verfügbare Tabellen</h5>
        <p class="rex-tile-text">' . $allowedTables . '</p>
    </div>
</div>';

// Queries heute
if ($apiEnabled) {
    $sql->setQuery('SELECT COUNT(*) as count FROM ' . rex::getTable('rexql_query_log') . ' WHERE DATE(createdate) = CURDATE()');
    $todayQueries = $sql->getValue('count');
} else {
    $todayQueries = 0;
}

$statusContent .= '<div class="col-sm-3">
    <div class="rex-tile">
        <h5>Queries heute</h5>
        <p class="rex-tile-text">' . $todayQueries . '</p>
    </div>
</div>';

$statusContent .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Status-Übersicht');
$fragment->setVar('body', $statusContent, false);
$content .= $fragment->parse('core/page/section.php');

// Quick Actions
$actionsContent = '<div class="btn-group" role="group">';

if (!$apiEnabled) {
    $actionsContent .= '<a href="' . rex_url::currentBackendPage(['page' => 'rexql/config']) . '" class="btn btn-primary">
        <i class="rex-icon rex-icon-settings"></i> API aktivieren
    </a>';
}

$actionsContent .= '<a href="' . rex_url::currentBackendPage(['page' => 'rexql/permissions']) . '" class="btn btn-default">
    <i class="rex-icon rex-icon-user"></i> API-Schlüssel verwalten
</a>';

if ($apiEnabled) {
    $actionsContent .= '<a href="' . rex_url::currentBackendPage(['page' => 'rexql/playground']) . '" class="btn btn-default">
        <i class="rex-icon rex-icon-action"></i> GraphQL Playground
    </a>';
}

$actionsContent .= '<a href="' . rex_url::currentBackendPage(['page' => 'rexql/docs']) . '" class="btn btn-default">
    <i class="rex-icon rex-icon-info"></i> Dokumentation
</a>';

$actionsContent .= '</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Aktionen');
$fragment->setVar('body', $actionsContent, false);
$content .= $fragment->parse('core/page/section.php');

// Endpoint-URL anzeigen wenn aktiviert
if ($apiEnabled) {
    $endpointUrl = FriendsOfRedaxo\RexQL\Utility::getEndpointUrl();
    $endpointUrlShort = FriendsOfRedaxo\RexQL\Utility::getEndpointUrl(true);

    $urlContent = '<p><strong>GraphQL Endpoint:</strong></p>
    <div style="display: flex; gap: 10px; padding: 8px;">
        <span>' . $endpointUrl . '</span>
        ' . FriendsOfRedaxo\RexQL\Utility::copyToClipboardButton($endpointUrl) . '
    </div>
    <div style="display: flex; gap: 10px; padding: 8px;">
        <span>' . $endpointUrlShort . '</span>
        ' . FriendsOfRedaxo\RexQL\Utility::copyToClipboardButton($endpointUrlShort) . '
    </div>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'GraphQL Endpoint');
    $fragment->setVar('body', $urlContent, false);
    $content .= $fragment->parse('core/page/section.php');
}

// Erste Schritte (wenn API nicht aktiviert)
if (!$apiEnabled) {
    $setupContent = '
    <h4>Erste Schritte</h4>
    <ol>
        <li><strong>API aktivieren:</strong> Gehen Sie zur <a href="' . rex_url::currentBackendPage(['page' => 'rexql/config']) . '">Konfiguration</a> und aktivieren Sie den API-Endpoint</li>
        <li><strong>Tabellen auswählen:</strong> Wählen Sie in der Konfiguration aus, welche Tabellen über die API verfügbar sein sollen</li>
        <li><strong>API-Schlüssel erstellen:</strong> Erstellen Sie in der <a href="' . rex_url::currentBackendPage(['page' => 'rexql/permissions']) . '">Berechtigungsverwaltung</a> einen API-Schlüssel</li>
        <li><strong>API testen:</strong> Nutzen Sie den <a href="' . rex_url::currentBackendPage(['page' => 'rexql/playground']) . '">GraphQL Playground</a> zum Testen</li>
        <li><strong>Integration:</strong> Integrieren Sie die API in Ihre Anwendung - siehe <a href="' . rex_url::currentBackendPage(['page' => 'rexql/docs']) . '">Dokumentation</a></li>
    </ol>
    
    <div class="alert alert-info">
        <h5>Voraussetzungen:</h5>
        <ul>
            <li><strong>graphql-php installiert:</strong> Führen Sie <code>composer install</code> im Addon-Verzeichnis aus</li>
            <li><strong>Abhängige Addons:</strong> YForm, URL und YRewrite sollten installiert sein für erweiterte Funktionen</li>
        </ul>
    </div>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Setup');
    $fragment->setVar('body', $setupContent, false);
    $content .= $fragment->parse('core/page/section.php');
}

echo $content;
