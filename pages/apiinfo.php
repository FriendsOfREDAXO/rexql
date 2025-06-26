<?php

/**
 * API Information Seite
 */

$addon = rex_addon::get('rexql');

if (!$addon->getConfig('endpoint_enabled', false)) {
  echo rex_view::warning(rex_i18n::msg('rexql_endpoint_not_enabled'));
  return;
}

$endpointUrl = FriendsOfRedaxo\RexQL\Utility::getEndpointUrl();

$content = '';

// Endpoint-Informationen
$infoContent = '
<p><strong>Endpoint URL:</strong> <code>' . $endpointUrl . '</code></p>
<p><strong>Unterstützte HTTP-Methoden:</strong> GET, POST</p>
<p><strong>Content-Type:</strong> application/json oder application/x-www-form-urlencoded</p>

<h5>Authentifizierung</h5>
<p>API-Schlüssel kann über folgende Wege übertragen werden:</p>
<ul>
    <li>HTTP-Header: <code>X-API-KEY: ihr_api_schluessel</code></li>
    <li>Authorization-Header: <code>Authorization: Bearer ihr_api_schluessel</code></li>
    <li>URL-Parameter: <code>?api_key=ihr_api_schluessel</code></li>
</ul>

<h5>Beispiel cURL-Request</h5>
<pre><code>curl -X POST ' . $endpointUrl . ' \\
  -H "Content-Type: application/json" \\
  -H "X-API-KEY: ihr_api_schluessel" \\
  -d \'{"query": "{ rexArticleList(limit: 5) { id name } }"}\'
</code></pre>

<h5>Rate Limiting</h5>
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
$fragment->setVar('title', 'API-Informationen');
$fragment->setVar('body', $infoContent, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;
