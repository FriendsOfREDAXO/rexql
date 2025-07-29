<?php

/**
 * rexQL Übersichtsseite
 */

use FriendsOfRedaxo\RexQL\EndpointType;
use FriendsOfRedaxo\RexQL\Utility;

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
    $endpointHtml = '';
    $endpointHtml .= '<thead><tr>
        <td>&nbsp;</td>
        <td>
            <strong>Standard</strong>
        </td>
        <td>
            <b>Kurz</b><br />
        </td>
    </tr></thead>';
    $endpointUrl = Utility::getEndpointUrl(EndpointType::Endpoint);
    $endpointUrlShort = Utility::getEndpointUrl(EndpointType::Endpoint, true);
    $endpointHtml .= '<tbody>';
    $endpointHtml .= '<tr>
        <td>
            <strong>Endpoint</strong>
        </td>
        <td>
            <div style="display: flex; gap: 10px;">
                <span>' . $endpointUrl . '</span>
                ' . Utility::copyToClipboardButton($endpointUrl) . '
            </div>
        </td>
        <td>
            <div style="display: flex; gap: 10px;">
                <span>' . $endpointUrlShort . '</span>
                ' . Utility::copyToClipboardButton($endpointUrlShort) . '
            </div>
        </td>
    </tr>';
    $endpointUrl = Utility::getEndpointUrl(EndpointType::Auth);
    $endpointUrlShort = Utility::getEndpointUrl(EndpointType::Auth, true);
    $endpointHtml .= '<tr>
        <td>
            <strong>Auth</strong>
        </td>
        <td>
            <div style="display: flex; gap: 10px;">
                <span>' . $endpointUrl . '</span>
                ' . Utility::copyToClipboardButton($endpointUrl) . '
            </div>
        </td>
        <td>
            <div style="display: flex; gap: 10px;">
                <span>' . $endpointUrlShort . '</span>
                ' . Utility::copyToClipboardButton($endpointUrlShort) . '
            </div>
        </td>
    </tr>';
    $endpointUrl = Utility::getEndpointUrl(EndpointType::Proxy);
    $endpointUrlShort = Utility::getEndpointUrl(EndpointType::Proxy, true);
    $endpointHtml .= '<tr>
        <td>
            <strong>Proxy</strong>
        </td>
        <td>
            <div style="display: flex; gap: 10px;">
                <span>' . $endpointUrl . '</span>
                ' . Utility::copyToClipboardButton($endpointUrl) . '
            </div>
        </td>
        <td>
            <div style="display: flex; gap: 10px;">
                <span>' . $endpointUrlShort . '</span>
                ' . Utility::copyToClipboardButton($endpointUrlShort) . '
            </div>
        </td>
    </tr>';
    $endpointHtml .= '</tbody>';

    $endpointHtml = '<table class="table table-borderless table-condensed table-hover">' . $endpointHtml . '</table>';

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


    $infosHtml = '<h3 style="margin-top:0">Unterstützte HTTP-Methoden:</h3>
    <p>GET, POST, OPTIONS</p>
    <h3>Content-Type:</h3>
    <p>application/json oder application/x-www-form-urlencoded</p>
';

    $infosHtml .= '<h3>Kurze URL-Varianten:</h3>
    <p>Anpassung an .htaccess bzw. Nginx Conf nötig!</p>
<p><strong>Apache .htaccess:</strong></p>
<pre><code>RewriteRule ^api/rexql(.*) %{ENV:BASE}/index.php?rex-api-call=rexql$1&%{QUERY_STRING} [L]</code></pre>
<p><strong>Nginx Conf:</strong></p>
<pre><code>location /api/rexql { 
    rewrite ^/api/rexql(.*) /index.php?rex-api-call=rexql$1 last; 
}</code></pre>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Detailinformationen');
    $fragment->setVar('body', $infosHtml, false);
    $content .= $fragment->parse('core/page/section.php');
}



echo $content;
