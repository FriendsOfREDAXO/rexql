<?php

/**
 * rexQL Dokumentation - aus README.md generiert
 */

$addon = rex_addon::get('rexql');
$content = '';

// README.md Inhalt laden und mit rex_markdown parsen
$readmePath = $addon->getPath('README.md');

if (file_exists($readmePath)) {
  $readmeContent = file_get_contents($readmePath);

  // Beispiele in der README aktualisieren (Legacy-Ersetzungen für alte Versionen)
  $readmeContent = str_replace('articles(', 'rexArticleList(', $readmeContent);
  $readmeContent = str_replace('article(', 'rexArticle(', $readmeContent);
  $readmeContent = str_replace('slices {', 'rexArticleSliceList {', $readmeContent);

  // Markdown zu HTML konvertieren
  if (class_exists('rex_markdown')) {
    $htmlContent = rex_markdown::factory()->parse($readmeContent);
  } else {
    // Fallback wenn rex_markdown nicht verfügbar ist
    $htmlContent = '<pre>' . rex_escape($readmeContent) . '</pre>';
  }

  $fragment = new rex_fragment();
  $fragment->setVar('title', 'Dokumentation');
  $fragment->setVar('body', $htmlContent, false);
  $content .= $fragment->parse('core/page/section.php');
} else {
  $content .= rex_view::error('README.md nicht gefunden');
}

// Zusätzliche Backend-spezifische Informationen
$backendInfo = '
<h2>Backend-Integration</h2>

<h3>GraphQL Playground</h3>
<p>Verwenden Sie den integrierten <a href="' . rex_url::currentBackendPage(['page' => 'rexql/playground']) . '">GraphQL Playground</a> zum Testen Ihrer Queries.</p>

<h3>API-Schlüssel verwalten</h3>
<p>API-Schlüssel können in der <a href="' . rex_url::currentBackendPage(['page' => 'rexql/permissions']) . '">Berechtigungsverwaltung</a> erstellt und verwaltet werden.</p>

<h3>Verfügbare Endpunkte</h3>
<p><strong>Haupt-Endpoint:</strong> <code>' . rtrim(rex::getServer(), '/') . '/index.php?rex-api-call=rexql_graphql</code></p>

<h3>Verfügbare Queries</h3>
<p>Die folgenden Query-Typen sind verfügbar:</p>
<ul>';

// Dynamisch verfügbare Tabellen anzeigen
$allowedTables = $addon->getConfig('allowed_tables', []);
if (!empty($allowedTables)) {
  foreach ($allowedTables as $table) {
    $typeName = str_replace(['rex_', '_'], ['', ''], ucwords($table, '_'));
    $queryName = lcfirst($typeName);
    $listQueryName = $queryName . 's';

    $backendInfo .= '<li><code>' . $queryName . '</code> - Einzelnen Datensatz aus ' . $table . ' abfragen</li>';
    $backendInfo .= '<li><code>' . $listQueryName . '</code> - Liste von Datensätzen aus ' . $table . ' abfragen</li>';
  }
} else {
  $backendInfo .= '<li><em>Keine Tabellen freigegeben. Konfigurieren Sie die verfügbaren Tabellen in den <a href="' . rex_url::currentBackendPage(['page' => 'rexql/config']) . '">Einstellungen</a>.</em></li>';
}

$backendInfo .= '</ul>';

$fragment = new rex_fragment();
$fragment->setVar('title', 'Backend-Integration');
$fragment->setVar('body', $backendInfo, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;
