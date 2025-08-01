<?php

/**
 * rexQL v1.0 Dokumentation
 * Lädt README.md mit REDAXO Markdown Parser
 */

$addon = rex_addon::get('rexql');
$content = '';

// Backend-Tools Header mit direkten Links
$backendTools = '
<div class="alert alert-info">
  <h4><i class="rex-icon rex-icon-info"></i> rexQL v1.0 - SDL-basierte GraphQL API</h4>
  <p>Eine vollständige GraphQL-API für REDAXO CMS mit SDL-Schema-Erweiterung, automatischer YForm-Integration und intelligentem Caching.</p>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><strong>🎯 Backend-Tools</strong></div>
  <div class="panel-body">
    <div class="btn-group" role="group">
      <a href="' . rex_url::currentBackendPage(['page' => 'rexql/playground']) . '" class="btn btn-primary">
        <i class="rex-icon rex-icon-module"></i> GraphQL Playground
      </a>
      <a href="' . rex_url::currentBackendPage(['page' => 'rexql/permissions']) . '" class="btn btn-default">
        <i class="rex-icon rex-icon-user"></i> Berechtigungen
      </a>
      <a href="' . rex_url::currentBackendPage(['page' => 'rexql/config']) . '" class="btn btn-default">
        <i class="rex-icon rex-icon-settings"></i> Konfiguration
      </a>
    </div>
    
    <hr>
    
    <h5>📡 API-Endpunkte</h5>
    <p><strong>Haupt-Endpoint:</strong> <code>' . rtrim(rex::getServer(), '/') . '/index.php?rex-api-call=rexql</code></p>
    <p><strong>Kurz-URL:</strong> <code>' . rtrim(rex::getServer(), '/') . '/api/rexql/</code> <small class="text-muted">(erfordert .htaccess/.nginx Regel)</small></p>
  </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'rexQL Backend-Tools');
$fragment->setVar('body', $backendTools, false);
$content .= $fragment->parse('core/page/section.php');

// README.md Inhalt laden und mit rex_markdown parsen
$readmePath = $addon->getPath('README.md');

if (file_exists($readmePath)) {
  $readmeContent = file_get_contents($readmePath);

  // Markdown zu HTML konvertieren
  if (class_exists('rex_markdown')) {
    $htmlContent = rex_markdown::factory()->parse($readmeContent);

    // Backend-Tools Sektion aus README entfernen da wir sie oben schon haben
    $htmlContent = preg_replace(
      '/<h3[^>]*>Backend-Tools.*?<\/ul>/s',
      '',
      $htmlContent
    );
  } else {
    // Fallback wenn rex_markdown nicht verfügbar ist
    $htmlContent = '<div class="alert alert-warning">
            <strong>Hinweis:</strong> Das rex_markdown Addon ist nicht installiert. 
            <a href="' . rex_url::currentBackendPage(['page' => 'packages']) . '">Installiere es</a> 
            für eine bessere Darstellung der Dokumentation.
        </div>
        <pre>' . rex_escape($readmeContent) . '</pre>';
  }

  $fragment = new rex_fragment();
  $fragment->setVar('title', 'Vollständige Dokumentation');
  $fragment->setVar('body', $htmlContent, false);
  $content .= $fragment->parse('core/page/section.php');
} else {
  $content .= rex_view::error('README.md nicht gefunden im Pfad: ' . $readmePath);
}

echo $content;
