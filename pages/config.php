<?php

/**
 * rexQL Konfiguration
 */

$addon = rex_addon::get('rexql');
$content = '<div class="rexql-config-section">';
$buttons = '';
$formElements = [];

// Handle cache refresh action
if (rex_post('action', 'string') === 'refresh_schema_cache') {
    FriendsOfRedaxo\RexQL\Cache::invalidateSchema();
    echo rex_view::success($addon->i18n('cache_refreshed', 'Schema cache successfully refreshed'));
}

if (rex_post('action', 'string') === 'refresh_all_cache') {
    FriendsOfRedaxo\RexQL\Cache::invalidateAll();
    echo rex_view::success($addon->i18n('cache_refreshed', 'All caches successfully refreshed'));
}

// Konfiguration speichern
if (rex_post('formsubmit', 'string') == '1') {
    $postConfig = rex_post('config', 'array', []);
    $config['endpoint_enabled'] = isset($postConfig['endpoint_enabled']) ? 1 : 0;
    $config['require_authentication'] = isset($postConfig['require_authentication']) ? 1 : 0;
    $config['rate_limit'] = (int)($postConfig['rate_limit'] ?? 0);
    $config['max_query_depth'] = (int)($postConfig['max_query_depth'] ?? 0);
    $config['introspection_enabled'] = isset($postConfig['introspection_enabled']) ? 1 : 0;
    $config['debug_mode'] = isset($postConfig['debug_mode']) ? 1 : 0;
    $config['cache_queries'] = isset($postConfig['cache_queries']) ? 1 : 0;
    $config['allowed_tables'] = $postConfig['allowed_tables'] ?? [];
    $config['proxy_enabled'] = isset($postConfig['proxy_enabled']) ? 1 : 0;
    $config['allow_public_dev'] = isset($postConfig['allow_public_dev']) ? 1 : 0;
    $config['cors_allowed_origins'] = array_filter(explode("\n", str_replace("\r", "", $postConfig['cors_allowed_origins'] ?? '*')));
    $config['cors_allowed_methods'] = $postConfig['cors_allowed_methods'] ?? ['GET', 'POST', 'OPTIONS'];
    $config['cors_allowed_headers'] = array_filter(explode("\n", str_replace("\r", "", $postConfig['cors_allowed_headers'] ?? '')));

    $addon->setConfig($config);

    // Cache invalidieren
    FriendsOfRedaxo\RexQL\Cache::invalidateAll();

    echo rex_view::success($addon->i18n('config_saved'));
}

// Werte aus der Konfiguration laden mit Standardwerten
$values = array_merge([
    'endpoint_enabled' => 0,
    'require_authentication' => 1,
    'rate_limit' => 100,
    'max_query_depth' => 10,
    'introspection_enabled' => 0,
    'debug_mode' => 0,
    'cache_queries' => 0,
    'allowed_tables' => [],
    'proxy_enabled' => 0,
    'allow_public_dev' => 0,
    'cors_allowed_origins' => ['*'],
    'cors_allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'cors_allowed_headers' => []
], $addon->getConfig());

// Development mode notice
$isDevMode = FriendsOfRedaxo\RexQL\Utility::isDevMode() || false;

if (!$isDevMode) {
    $isDevMode = (method_exists('rex', 'isDebugMode') && rex::isDebugMode()) ||
        (isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', 'redaxo-graph-ql.test']));
}

if ($isDevMode) {
    $content .= '<div class="rexql-config-dev-notice">
        <i class="fa fa-info-circle"></i>
        <strong>' . $addon->i18n('dev_mode_notice') . '</strong><br>
        <small>' . $addon->i18n('dev_mode_warning') . '</small>
    </div>';
}

// Beginn des Formulars
$content .= '<fieldset>';
$content .= '<legend>' . $addon->i18n('config_general') . '</legend>';

// Endpoint aktivieren
$formElements = [];
$n = [];
$n['label'] = '<label for="endpoint_enabled">' . $addon->i18n('config_endpoint_enabled') . '</label>';
$n['field'] = '<input type="checkbox" id="endpoint_enabled" name="config[endpoint_enabled]" value="1" ' . ($values['endpoint_enabled'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Authentifizierung erforderlich
$n = [];
$n['label'] = '<label for="require_authentication">' . $addon->i18n('config_require_authentication') . '</label>';
$n['field'] = '<input type="checkbox" id="require_authentication" name="config[require_authentication]" value="1" ' . ($values['require_authentication'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Rate Limit
$n = [];
$n['label'] = '<label for="rate_limit">' . $addon->i18n('config_rate_limit') . '</label>';
$n['field'] = '<input type="number" id="rate_limit" name="config[rate_limit]" class="rexql-config-input" value="' . $values['rate_limit'] . '" min="1" max="10000" />'
    . '<small class="rexql-field-hint">Number of API requests allowed per minute per API key.</small>';
$formElements[] = $n;

// Maximale Query-Tiefe
$n = [];
$n['label'] = '<label for="max_query_depth">' . $addon->i18n('config_max_query_depth') . '</label>';
$n['field'] = '<input type="number" id="max_query_depth" name="config[max_query_depth]" class="rexql-config-input" value="' . $values['max_query_depth'] . '" min="1" max="50" />'
    . '<small class="rexql-field-hint">Maximum nesting depth allowed for GraphQL queries to prevent overly complex queries.</small>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$content .= '</fieldset>';

// Erweiterte Einstellungen
$content .= '<fieldset>';
$content .= '<legend>' . $addon->i18n('config_advanced') . '</legend>';

$formElements = [];

// Introspection aktivieren
$n = [];
$n['label'] = '<label for="introspection_enabled">' . $addon->i18n('config_introspection_enabled') . '</label>';
$n['field'] = '<input type="checkbox" id="introspection_enabled" name="config[introspection_enabled]" value="1" ' . ($values['introspection_enabled'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Debug-Modus
$n = [];
$n['label'] = '<label for="debug_mode">' . $addon->i18n('config_debug_mode') . '</label>';
$n['field'] = '<input type="checkbox" id="debug_mode" name="config[debug_mode]" value="1" ' . ($values['debug_mode'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Cache aktivieren
$n = [];
$n['label'] = '<label for="cache_queries">' . $addon->i18n('config_cache_queries') . '</label>';
$n['field'] = '<input type="checkbox" id="cache_queries" name="config[cache_queries]" value="1" ' . ($values['cache_queries'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Proxy aktivieren
$n = [];
$n['label'] = '<label for="proxy_enabled">' . $addon->i18n('config_proxy_enabled') . '</label>';
$n['field'] = '<input type="checkbox" id="proxy_enabled" name="config[proxy_enabled]" value="1" ' . ($values['proxy_enabled'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Öffentliches Dev erlauben
$n = [];
$n['label'] = '<label for="allow_public_dev">' . $addon->i18n('config_allow_public_dev') . '</label>';
$n['field'] = '<input type="checkbox" id="allow_public_dev" name="config[allow_public_dev]" value="1" ' . ($values['allow_public_dev'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$content .= '</fieldset>';

// CORS Einstellungen
$content .= '<fieldset>';
$content .= '<legend>' . $addon->i18n('config_cors') . '</legend>';
$content .= '<div class="rexql-config-security-note">
    <i class="fa fa-shield"></i>
    ' . $addon->i18n('config_headless_security_notice') . '
</div>';

$formElements = [];

// CORS Origins
$n = [];
$n['label'] = '<label for="cors_allowed_origins">' . $addon->i18n('config_cors_allowed_origins') . '</label>';
$corsOrigins = is_array($values['cors_allowed_origins']) ? implode("\n", $values['cors_allowed_origins']) : '*';
$n['field'] = '<textarea id="cors_allowed_origins" name="config[cors_allowed_origins]" class="rexql-cors-textarea" rows="5" placeholder="https://your-domain.com&#10;https://app.your-domain.com&#10;http://localhost:3000&#10;*">' . rex_escape($corsOrigins) . '</textarea>'
    . '<small class="rexql-field-hint">Enter allowed origins for CORS requests. Use * to allow all origins (not recommended for production).</small>';
$formElements[] = $n;

// CORS Methods
$n = [];
$n['label'] = '<label for="cors_allowed_methods">' . $addon->i18n('config_cors_allowed_methods') . '</label>';
$corsMethods = is_array($values['cors_allowed_methods']) ? $values['cors_allowed_methods'] : ['GET', 'POST', 'OPTIONS'];
$methodOptions = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'HEAD', 'PATCH'];
$methodCheckboxes = '<div class="rexql-cors-methods">';
foreach ($methodOptions as $method) {
    $checked = in_array($method, $corsMethods) ? ' checked="checked"' : '';
    $methodCheckboxes .= '<label class="checkbox-inline"><input type="checkbox" name="config[cors_allowed_methods][]" value="' . $method . '"' . $checked . '> <span>' . $method . '</span></label>';
}
$methodCheckboxes .= '</div>';
$methodCheckboxes .= '<small class="rexql-field-hint">Select HTTP methods that are allowed for CORS requests. GET, POST, and OPTIONS are recommended for GraphQL.</small>';
$n['field'] = $methodCheckboxes;
$formElements[] = $n;

// CORS Headers
$n = [];
$n['label'] = '<label for="cors_allowed_headers">' . $addon->i18n('config_cors_allowed_headers') . '</label>';
$corsHeaders = is_array($values['cors_allowed_headers']) ? implode("\n", $values['cors_allowed_headers']) : "Content-Type\nAuthorization\nX-API-KEY\nX-Public-Key";
$n['field'] = '<textarea id="cors_allowed_headers" name="config[cors_allowed_headers]" class="rexql-cors-textarea" rows="5" placeholder="Content-Type&#10;Authorization&#10;X-API-KEY&#10;X-Public-Key">' . rex_escape($corsHeaders) . '</textarea>'
    . '<small class="rexql-field-hint">Headers that are allowed in CORS requests. These are commonly needed for GraphQL APIs.</small>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$content .= '</fieldset>';

// Tabellen-Konfiguration
$content .= '<fieldset>';
$content .= '<legend>' . $addon->i18n('config_tables') . '</legend>';

$content .= '<div class="form-group">
    <label class="control-label">' . $addon->i18n('config_allowed_tables') . '</label>
    <div class="rex-form-panel-content">';

// Core-Tabellen
$allowedTables = is_array($values['allowed_tables']) ? $values['allowed_tables'] : [];
$coreTables = [
    'rex_action' => 'Aktionen',
    'rex_article' => 'Artikel',
    'rex_article_slice' => 'Artikel-Slices',
    'rex_clang' => 'Sprachen',
    'rex_media' => 'Medien',
    'rex_media_category' => 'Medienkategorien',
    'rex_module' => 'Module',
    'rex_template' => 'Templates',
];

$content .= '<h5>' . $addon->i18n('config_core_tables') . '</h5>';
$content .= '
<div class="checkbox">
    <label>
        <input type="checkbox" id="select_all_core_tables" class="table-checkbox-all">
        <strong>' . $addon->i18n('config_select_all') . '</strong>
    </label>
</div>';
foreach ($coreTables as $table => $label) {
    $checked = in_array($table, $allowedTables) ? ' checked="checked"' : '';
    $content .= '
        <div class="checkbox">
            <label>
                <input type="checkbox" name="config[allowed_tables][]" class="core-table table-checkbox" value="' . $table . '" ' . $checked . '>
                ' . $label . ' (' . $table . ')
            </label>
        </div>';
}

// YForm-Tabellen (falls verfügbar)
if (rex_addon::get('yform')->isAvailable()) {
    $tables = rex_yform_manager_table::getAll();
    if (count($tables) > 0) {
        $content .= '<h5>' . $addon->i18n('config_yform_tables') . '</h5>';
        $content .= '
    <div class="checkbox">
        <label>
            <input type="checkbox" id="select_all_yform_tables" class="table-checkbox-all">
            <strong>' . $addon->i18n('config_select_all') . '</strong>
        </label>
    </div>';
        foreach ($tables as $table) {
            $tableName = $table->getTableName();
            $checked = in_array($tableName, $allowedTables) ? ' checked="checked"' : '';
            $content .= '
              <div class="checkbox">
                  <label>
                      <input type="checkbox" name="config[allowed_tables][]" class="yform-table table-checkbox" value="' . $tableName . '"' . $checked . '>
                      ' . ($table->getName() ?: $tableName) . ' (' . $tableName . ')
                  </label>
              </div>';
        }
    }
}

$content .= '</div></div>';
$content .= '</fieldset>';

// Cache Management Section
$cacheStatus = FriendsOfRedaxo\RexQL\Cache::getStatus();
$content .= '<fieldset>';
$content .= '<legend>' . $addon->i18n('cache_management', 'Cache Management') . '</legend>';
$content .= '<div class="row">';
$content .= '<div class="col-md-6">';
$content .= '<h4>' . $addon->i18n('cache_status', 'Cache Status') . '</h4>';
$content .= '<ul class="list-unstyled">';
$content .= '<li><strong>' . $addon->i18n('schema_version', 'Schema Version') . ':</strong> ' . $cacheStatus['schema_version'] . '</li>';
$content .= '<li><strong>' . $addon->i18n('query_caching', 'Query Caching') . ':</strong> ' . ($cacheStatus['query_caching_enabled'] ? $addon->i18n('enabled', 'Enabled') : $addon->i18n('disabled', 'Disabled')) . '</li>';
$content .= '<li><strong>' . $addon->i18n('schema_cache_files', 'Schema Cache Files') . ':</strong> ' . $cacheStatus['schema_cache_files'] . '</li>';
$content .= '<li><strong>' . $addon->i18n('query_cache_files', 'Query Cache Files') . ':</strong> ' . $cacheStatus['query_cache_files'] . '</li>';
$content .= '</ul>';
$content .= '</div>';
$content .= '<div class="col-md-6">';
$content .= '<h4>' . $addon->i18n('cache_actions', 'Cache Actions') . '</h4>';
$content .= '<p><small>' . $addon->i18n('cache_help', 'Refresh the schema cache when you install/uninstall addons or modify YForm table structures.') . '</small></p>';
$content .= '<div class="btn-group-vertical" style="width: 100%;">';
$content .= '<button type="submit" name="action" value="refresh_schema_cache" class="btn btn-warning">';
$content .= '<i class="fa fa-refresh"></i> ' . $addon->i18n('refresh_schema_cache', 'Refresh Schema Cache');
$content .= '</button>';
$content .= '<button type="submit" name="action" value="refresh_all_cache" class="btn btn-danger">';
$content .= '<i class="fa fa-trash"></i> ' . $addon->i18n('refresh_all_cache', 'Clear All Caches');
$content .= '</button>';
$content .= '</div>';
$content .= '</div>';
$content .= '</div>';
$content .= '</fieldset>';

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $addon->i18n('save') . '">' . $addon->i18n('save') . '</button>';
$formElements[] = $n;
$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');
$buttons = '
<fieldset class="rex-form-action">
    ' . $buttons . '
</fieldset>
';

$content .= '</div>'; // Close rexql-config-section

// Ausgabe Formular
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('config_title'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = $fragment->parse('core/page/section.php');

echo '
<form action="' . rex_url::currentBackendPage() . '" method="post">
<input type="hidden" name="formsubmit" value="1" />
    ' . $output . '
</form>
';
