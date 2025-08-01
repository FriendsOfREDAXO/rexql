<?php

/**
 * rexQL - GraphQL API for REDAXO CMS
 * 
 * @var rex_addon $this
 * @psalm-scope-this rex_addon
 */

namespace FriendsOfRedaxo\RexQL;

use FriendsOfRedaxo\RexQL\Api\Endpoint;
use FriendsOfRedaxo\RexQL\Api\Proxy;
use FriendsOfRedaxo\RexQL\Api\Auth;
use FriendsOfRedaxo\RexQL\RexQL;
use FriendsOfRedaxo\RexQL\Services\Extensions;

use rex;
use rex_api_function;
use rex_be_controller;
use rex_extension;
use rex_perm;
use rex_view;

// Register permissions
rex_perm::register('rexql[]');
rex_perm::register('rexql[config]');
rex_perm::register('rexql[permissions]');
rex_perm::register('rexql[webhooks]');

// Register API classes
rex_api_function::register('rexql', Endpoint::class);
rex_api_function::register('rexql_proxy', Proxy::class);
rex_api_function::register('rexql_auth', Auth::class);

// Set default configuration
if (!$this->hasConfig()) {
  $this->setConfig([
    'rexql_url' => '/index.php?rex-api-call=rexql',
    'rexql_proxy_url' => '/index.php?rex-api-call=rexql_proxy',
    'rexql_auth_url' => '/index.php?rex-api-call=rexql_auth',
    'endpoint_enabled' => false,
    'proxy_enabled' => false,
    'cache_enabled' => true,
    'require_authentication' => true,
    'max_query_depth' => 10,
    'cors_allowed_origins' => ['*'],          // CORS Origins
    'cors_allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'cors_allowed_headers' => ['Content-Type', 'Authorization', 'X-API-KEY', 'X-Public-Key'],
    'test_users' => [                         // For test authentication
      'testuser' => 'testpass',
      'demo' => 'demo123'
    ],
    'debug_mode' => false,
  ]);
}


rex::setProperty('rexql_addon', $this);

// Register extension points for Webhooks
rex_extension::register('PACKAGES_INCLUDED', Extensions::registerWebhookEps(...));

// Load backend assets only in backend
if (rex::isBackend() && rex::getUser()) {

  rex_view::addCssFile($this->getAssetsUrl('rexql.css'));

  $currentPage = rex_be_controller::getCurrentPage();
  $isRexQLPage = str_starts_with($currentPage, 'rexql');

  if ($isRexQLPage) {
    $api = new RexQL(true);
    rex::setProperty('rexql_api', $api);
  }
}
