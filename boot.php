<?php

/**
 * rexQL - GraphQL API for REDAXO CMS
 * 
 * @var rex_addon $this
 * @psalm-scope-this rex_addon
 */

require_once __DIR__ . '/vendor/autoload.php';

\rex_fragment::addDirectory(\rex_path::src('fragments'));


// Register permissions
rex_perm::register('rexql[graphql]', null, rex_perm::OPTIONS);
rex_perm::register('rexql[admin]', 'rexql[graphql]');

// Register API classes
rex_api_function::register('rexql_graphql', 'rex_api_rexql_graphql');
rex_api_function::register('rexql_proxy', 'rex_api_rexql_proxy');
rex_api_function::register('rexql_auth', 'rex_api_rexql_auth');

// Set default configuration
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
    'valid_session_tokens' => [],             // For simple session tokens
    'test_users' => [                         // For test authentication
      'testuser' => 'testpass',
      'demo' => 'demo123'
    ],
    'debug_mode' => false,
  ]);
}
// Set endpoint URL if not configured
if (!$this->getConfig('endpoint_url')) {
  $this->setConfig('endpoint_url', '/index.php?rex-api-call=rexql_graphql');
}

// Register extensions - only for existing extension points
rex_extension::register('PACKAGES_INCLUDED', function () {
  // Language changes (as they affect the schema cache key)
  rex_extension::register('CLANG_UPDATED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('CLANG_DELETED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');
  rex_extension::register('CLANG_ADDED', 'FriendsOfRedaxo\\RexQL\\Cache::invalidateSchema');

  // Note: Addon installation/table structure changes require manual cache invalidation
  // via the "Refresh Schema Cache" button in the rexQL backend
});

// Load backend assets only in backend
if (rex::isBackend() && rex::getUser()) {
  rex_view::addCssFile($this->getAssetsUrl('rexql.css'));
  rex_view::addJsFile($this->getAssetsUrl('rexql.js'));
}
