<?php

/**
 * rexQL - GraphQL API for REDAXO CMS
 * 
 * @var rex_addon $this
 * @psalm-scope-this rex_addon
 */

\rex_fragment::addDirectory(\rex_path::src('fragments'));


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
          FriendsOfRedaxo\RexQL\Cache::invalidate();
          break;
        default:
          FriendsOfRedaxo\RexQL\Cache::invalidate('query');
          break;
      }
      $params = $ep->getParams();
      $params['subject'] = $ep->getSubject();
      $params['extension_point'] = $ep->getName();
      FriendsOfRedaxo\RexQL\Webhook::send($params);
    }, rex_extension::LATE);
  }
});

// Load backend assets only in backend
if (rex::isBackend() && rex::getUser()) {

  if ('codemirror' == rex_request('rexql_output', 'string', '')) {
    rex_response::cleanOutputBuffers();
    header('Content-Type: application/javascript');
    $plugin = rex_plugin::get('be_style', 'customizer');
    $filenames = [];
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/codemirror.min.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/display/autorefresh.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/display/fullscreen.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/selection/active-line.js');

    if (isset($config['codemirror-tools']) && $config['codemirror-tools']) {
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/foldcode.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/foldgutter.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/brace-fold.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/xml-fold.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/indent-fold.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/markdown-fold.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/fold/comment-fold.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/edit/closebrackets.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/edit/matchtags.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/edit/matchbrackets.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/mode/overlay.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/dialog/dialog.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/search/searchcursor.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/search/search.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/scroll/annotatescrollbar.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/search/matchesonscrollbar.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/addon/search/jump-to-line.js');
    }

    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/xml/xml.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/htmlmixed/htmlmixed.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/htmlembedded/htmlembedded.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/javascript/javascript.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/css/css.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/clike/clike.js');
    $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/php/php.js');

    if (isset($config['codemirror-langs']) && $config['codemirror-langs']) {
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/markdown/markdown.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/textile/textile.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/gfm/gfm.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/yaml/yaml.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/yaml-frontmatter/yaml-frontmatter.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/meta.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/properties/properties.js');
      $filenames[] = $plugin->getAssetsUrl('vendor/codemirror/mode/sql/sql.js');
    }
    $filenames[] = $this->getAssetsUrl('graphql.umd.js');

    $content = '';
    foreach ($filenames as $filename) {
      $content .= '/* ' . $filename . ' */' . "\n" . rex_file::get($filename) . "\n";
    }

    header('Pragma: cache');
    header('Cache-Control: public');
    header('Expires: ' . date('D, j M Y', strtotime('+1 week')) . ' 00:00:00 GMT');
    echo $content;

    exit;
  }
  rex_view::addCssFile($this->getAssetsUrl('rexql.css'));
  rex_view::addJsFile($this->getAssetsUrl('rexql.js'), [rex_view::JS_IMMUTABLE => true]);
}
