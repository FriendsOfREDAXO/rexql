<?php

/**
 * rexQL Übersichtsseite
 */

$addon = rex_addon::get('rexql');

$apiEnabled = $addon->getConfig('endpoint_enabled', false);

$content = '';

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

// Status-Übersicht
$statusContent = '<div class="row">';

// API Status
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


// Endpoint-URL anzeigen wenn aktiviert
if ($apiEnabled) {
    $endpointUrl = FriendsOfRedaxo\RexQL\Utility::getEndpointUrl();
    $endpointUrlShort = FriendsOfRedaxo\RexQL\Utility::getEndpointUrl(true);

    $endpointHtml = '
    <div style="display: flex; gap: 10px;">
        <div style="display: flex; gap: 10px; padding: 8px;">
            <span>' . $endpointUrlShort . '</span>
            ' . FriendsOfRedaxo\RexQL\Utility::copyToClipboardButton($endpointUrlShort) . '
        </div>
        <div style="display: flex; gap: 10px; padding: 8px;">
            <span>' . $endpointUrl . '</span>
            ' . FriendsOfRedaxo\RexQL\Utility::copyToClipboardButton($endpointUrl) . '
        </div>
    </div>
    <h3><strong>Unterstützte HTTP-Methoden:</strong> GET, POST</h3>
<p><strong>Content-Type:</strong> application/json oder application/x-www-form-urlencoded</p>
';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'GraphQL Endpoint');
    $fragment->setVar('body', $endpointHtml, false);
    $content .= $fragment->parse('core/page/section.php');


    // Endpoint-Informationen
    $authHtml = '<p>API-Schlüssel kann über folgende Wege übertragen werden:</p>
<ul>
    <li>HTTP-Header: <code>X-API-KEY: ihr_api_schluessel</code></li>
    <li>Authorization-Header: <code>Authorization: Bearer ihr_api_schluessel</code></li>
    <li>URL-Parameter: <code>?api_key=ihr_api_schluessel</code></li>
</ul>

<h3>Beispiel cURL-Request</h3>
<pre><code>curl -X POST ' . $endpointUrl . ' \\
  -H "Content-Type: application/json" \\
  -H "X-API-KEY: ihr_api_schluessel" \\
  -d \'{"query": "{ rexArticleList(limit: 5) { id name } }"}\'
</code></pre>

<h3>Rate Limiting</h3>
<p>Das API implementiert Rate Limiting basierend auf den Einstellungen der API-Schlüssel.</p>

<h5>Fehlerbehandlung</h5>
<p>Alle Antworten folgen dem GraphQL-Standard mit <code>data</code> und <code>errors</code> Feldern.</p>
<pre><code>{
  "data": { /* Ihre Daten */ },
  "errors": [
    { "message": "Fehlerbeschreibung" }
  ]
}
</code></pre>
';
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Authentifizierung');
    $fragment->setVar('body', $authHtml, false);
    $content .= $fragment->parse('core/page/section.php');
}


echo $content;
