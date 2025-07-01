<?php

/**
 * rexQL - GraphQL API for REDAXO CMS
 * 
 * @var rex_addon $this
 * @psalm-scope-this rex_addon
 */

require_once __DIR__ . '/vendor/autoload.php';

\rex_fragment::addDirectory(\rex_path::src('fragments'));


// Permissions registrieren
rex_perm::register('rexql[graphql]', null, rex_perm::OPTIONS);
rex_perm::register('rexql[admin]', 'rexql[graphql]');

// API-Klassen registrieren
rex_api_function::register('rexql_graphql', 'rex_api_rexql_graphql');
rex_api_function::register('rexql_proxy', 'rex_api_rexql_proxy');
rex_api_function::register('rexql_auth', 'rex_api_rexql_auth');

// Standardkonfiguration setzen
if (!$this->hasConfig()) {
  $this->setConfig([
    'schema_version' => 1,
    'endpoint_enabled' => false,
    'proxy_enabled' => false,
    'require_authentication' => true,
    'allow_public_access_in_dev' => true,     // In Dev-Modus ohne Auth erlauben
    'allowed_tables' => [],
    'rate_limit' => 100,
    'max_query_depth' => 10,
    'introspection_enabled' => false,
    'cors_allowed_origins' => ['*'],          // CORS Origins
    'cors_allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'cors_allowed_headers' => ['Content-Type', 'Authorization', 'X-API-KEY', 'X-Public-Key'],
    'valid_session_tokens' => [],             // Für einfache Session-Token
    'test_users' => [                         // Für Test-Authentifizierung
      'testuser' => 'testpass',
      'demo' => 'demo123'
    ],
    'debug_mode' => false,
  ]);
}
// endpunkt URL setzen, falls nicht konfiguriert
if (!$this->getConfig('endpoint_url')) {
  $this->setConfig('endpoint_url', '/index.php?rex-api-call=rexql_graphql');
}

// Extensions registrieren
rex_extension::register('PACKAGES_INCLUDED', function () {
  // Schema Cache nur bei Strukturänderungen löschen (nicht bei Datenänderungen)

  // Addon/Package-Änderungen
  rex_extension::register('ADDON_INSTALLED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('ADDON_UNINSTALLED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('ADDON_ACTIVATED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('ADDON_DEACTIVATED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');

  // YForm-Manager Table-Struktur-Änderungen
  rex_extension::register('YFORM_MANAGER_TABLE_UPDATED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('YFORM_MANAGER_TABLE_DELETED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('YFORM_MANAGER_FIELD_UPDATED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('YFORM_MANAGER_FIELD_DELETED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');

  // Language-Änderungen (da sie die Schema-Cache-Key beeinflussen)
  rex_extension::register('CLANG_UPDATED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('CLANG_DELETED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('CLANG_ADDED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
});

// Backend-Assets nur im Backend laden
if (rex::isBackend() && rex::getUser()) {
  rex_view::addCssFile($this->getAssetsUrl('rexql.css'));
  rex_view::addJsFile($this->getAssetsUrl('rexql.js'));
}
