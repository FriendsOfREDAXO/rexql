<?php

/**
 * rexQL - GraphQL API for REDAXO CMS
 * 
 * @var rex_addon $this
 * @psalm-scope-this rex_addon
 */

use FriendsOfRedaxo\RexQL\Cache;
use FriendsOfRedaxo\RexQL\Webhook;
use rex;
use rex_api_function;
use rex_extension;
use rex_fragment;
use rex_perm;
use rex_view;

rex_fragment::addDirectory(\rex_path::src('fragments'));


// Register permissions
rex_perm::register('rexql[graphql]', null, rex_perm::OPTIONS);
rex_perm::register('rexql[admin]', 'rexql[graphql]');
rex_perm::register('rexql[webhooks]', 'rexql[admin]');

// Register API classes
rex_api_function::register('rexql_graphql', 'FriendsOfRedaxo\RexQL\Api\rex_api_rexql_graphql');
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

  $extensionPoints = [
    'ART_ADDED',
    'ART_DELETED',
    'ART_MOVED',
    'ART_STATUS',
    'ART_UPDATED',
    'ART_SLICES_COPY',
    'SLICE_ADDED',
    'SLICE_UPDATE',
    'SLICE_MOVE',
    'SLICE_DELETE',
    'SLICE_STATUS',
    'CAT_ADDED',
    'CAT_DELETED',
    'CAT_MOVED',
    'CAT_STATUS',
    'CAT_UPDATED',
    'CLANG_ADDED',
    'CLANG_DELETED',
    'CLANG_UPDATED',
    'CACHE_DELETED',
    'REX_FORM_SAVED',
    'REX_YFORM_SAVED',
    'YFORM_DATA_ADDED',
    'YFORM_DATA_DELETED',
    'YFORM_DATA_UPDATED',
  ];

  foreach ($extensionPoints as $extensionPoint) {

    rex_extension::register($extensionPoint, function ($ep) {
      // Send webhook
      switch ($ep->getName()) {
        case 'CLANG_ADDED':
        case 'CLANG_DELETED':
        case 'CLANG_UPDATED':
        case 'CACHE_DELETED':
        case 'REX_FORM_SAVED':
          Cache::invalidate();
          break;
        default:
          Cache::invalidate('query');
          break;
      }
      $params = $ep->getParams();
      $params['subject'] = $ep->getSubject();
      $params['extension_point'] = $ep->getName();
      Webhook::send($params);
    }, rex_extension::LATE);
  }
});

// Load backend assets only in backend
if (rex::isBackend() && rex::getUser()) {

  rex_view::addCssFile($this->getAssetsUrl('rexql.css'));
}
