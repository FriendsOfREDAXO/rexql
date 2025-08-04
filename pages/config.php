<?php

/**
 * rexQL Konfiguration
 */

use FriendsOfRedaxo\RexQL\Cache;
use FriendsOfRedaxo\RexQL\Utility;

$addon = rex_addon::get('rexql');
$formSaved = '';

// Konfiguration speichern
if (rex_post('formsubmit', 'string') == 'config' && !rex_post('action', 'string', '')) {
    $post = rex_post('config', 'array', []);
    $config['endpoint_enabled'] = isset($post['endpoint_enabled']) ? 1 : 0;
    $config['require_authentication'] = isset($post['require_authentication']) ? 1 : 0;
    $config['max_query_depth'] = (int)($post['max_query_depth'] ?? 0);
    $config['debug_mode'] = isset($post['debug_mode']) ? 1 : 0;
    $config['cache_enabled'] = isset($post['cache_enabled']) ? 1 : 0;
    $config['cache_ttl'] = (int)($post['cache_ttl'] ?? 300);
    $config['proxy_enabled'] = isset($post['proxy_enabled']) ? 1 : 0;
    $addon->setConfig($config);
    $formSaved = 'config';
}

if (rex_post('formsubmit', 'string') == 'cors' && !rex_post('action', 'string', '')) {
    $post = rex_post('config', 'array', []);
    $config['cors_allowed_origins'] = array_filter(explode("\n", str_replace("\r", "", $post['cors_allowed_origins'] ?? '*')));
    $config['cors_allowed_methods'] = $post['cors_allowed_methods'] ?? ['GET', 'POST', 'OPTIONS'];
    $config['cors_allowed_headers'] = array_filter(explode("\n", str_replace("\r", "", $post['cors_allowed_headers'] ?? '')));
    $addon->setConfig($config);
    $formSaved = 'cors';
}

// Handle cache refresh action
if (rex_post('action', 'string') === 'refresh_cache') {
    Cache::invalidate();
    echo rex_view::success($addon->i18n('cache_refreshed', 'Cache successfully refreshed'));
}

// Werte aus der Konfiguration laden mit Standardwerten
$values = array_merge([
    'endpoint_enabled' => 0,
    'require_authentication' => 1,
    'max_query_depth' => 10,
    'debug_mode' => 0,
    'cache_enabled' => 0,
    'cache_ttl' => 300,
    'proxy_enabled' => 0,
    'cors_allowed_origins' => ['*'],
    'cors_allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'cors_allowed_headers' => []
], $addon->getConfig());

// Development mode notice
$isAuthEnabled = Utility::isAuthEnabled();


$content = '';
$buttons = '';
$formElements = [];

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $addon->i18n('save') . '">' . $addon->i18n('save') . '</button>';
$formElements[] = $n;
$n = [];
$n['field'] = '<button value="refresh_cache" class="btn btn-danger" type="submit" name="action" value="refresh_cache">' . $addon->i18n('refresh_all_cache') . '</button>';
$formElements[] = $n;
$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');


if (!$isAuthEnabled) {
    $content .= '<div class="rexql-config-auth-notice">
        <i class="fa fa-info-circle"></i>
        <strong>' . $addon->i18n('auth_notice') . '</strong><br>
        <small>' . $addon->i18n('auth_warning') . '</small>
    </div>';
}

// Beginn des Formulars
$content = '<fieldset>';

if ($formSaved === 'config') {
    $content .= rex_view::success($addon->i18n('config_saved'));
}

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

// Maximale Query-Tiefe
$n = [];
$n['label'] = '<label for="max_query_depth">' . $addon->i18n('config_max_query_depth') . '</label>';
$n['field'] = '<input type="number" id="max_query_depth" name="config[max_query_depth]" class="rexql-config-input" value="' . $values['max_query_depth'] . '" min="1" max="50" />'
    . '<small class="rexql-field-hint">Maximum nesting depth allowed for GraphQL queries to prevent overly complex queries.</small>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Erweiterte Einstellungen
$content .= '<h3>' . $addon->i18n('config_advanced') . '</h3>';

$formElements = [];

// Debug-Modus
$n = [];
$n['label'] = '<label for="debug_mode">' . $addon->i18n('config_debug_mode') . '</label>';
$n['field'] = '<input type="checkbox" id="debug_mode" name="config[debug_mode]" value="1" ' . ($values['debug_mode'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

// Cache aktivieren
$n = [];
$n['label'] = '<label for="cache_enabled">' . $addon->i18n('config_cache_enabled') . '</label>';
$n['field'] = '<input type="checkbox" id="cache_enabled" name="config[cache_enabled]" value="1" ' . ($values['cache_enabled'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="cache_ttl">' . $addon->i18n('config_cache_ttl') . '</label>';
$n['field'] = '<input type="number" id="cache_ttl" name="config[cache_ttl]" class="rexql-config-input" value="' . $values['cache_ttl'] . '" min="1" />'
    . '<small class="rexql-field-hint">' . $addon->i18n('config_cache_ttl_hint') . '</small>';
$formElements[] = $n;

// Proxy aktivieren
$n = [];
$n['label'] = '<label for="proxy_enabled">' . $addon->i18n('config_proxy_enabled') . '</label>';
$n['field'] = '<input type="checkbox" id="proxy_enabled" name="config[proxy_enabled]" value="1" ' . ($values['proxy_enabled'] ? ' checked="checked"' : '') . ' />';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$content .= '</fieldset>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('config_title'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = '<form action="' . rex_url::currentBackendPage() . '" method="post">';
$output .= $fragment->parse('core/page/section.php');
$output .= '<input type="hidden" name="formsubmit" value="config" />';
$output .= '</form>';
echo $output;

// CORS Einstellungen
$content = '<fieldset>';

if ($formSaved === 'cors') {
    $content .= rex_view::success($addon->i18n('config_saved'));
}
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
    . '<small class="rexql-field-hint">' . $addon->i18n('config_cors_allowed_origins_hint') . '<br />' . $addon->i18n('one_per_line') . '</small>';
$formElements[] = $n;

// CORS Methods
$n = [];
$n['label'] = '<label for="cors_allowed_methods">' . $addon->i18n('config_cors_allowed_methods') . '</label>';
$corsMethods = is_array($values['cors_allowed_methods']) ? $values['cors_allowed_methods'] : ['GET', 'POST', 'OPTIONS'];
$methodOptions = ['GET', 'POST', 'OPTIONS'];
$methodCheckboxes = '<div class="checkbox">';
foreach ($methodOptions as $method) {
    $checked = in_array($method, $corsMethods) ? ' checked="checked"' : '';
    $methodCheckboxes .= '<label class="checkbox-inline"><input type="checkbox" name="config[cors_allowed_methods][]" value="' . $method . '"' . $checked . '> <span>' . $method . '</span></label>';
}
$methodCheckboxes .= '</div>';
$methodCheckboxes .= '<small class="rexql-field-hint">' . $addon->i18n('config_cors_allowed_methods_hint') . '</small>';
$n['field'] = $methodCheckboxes;
$formElements[] = $n;

// CORS Headers
$n = [];
$n['label'] = '<label for="cors_allowed_headers">' . $addon->i18n('config_cors_allowed_headers') . '</label>';
$corsHeaders = is_array($values['cors_allowed_headers']) ? implode("\n", $values['cors_allowed_headers']) : "Content-Type\nAuthorization\nX-API-KEY\nX-Public-Key";
$n['field'] = '<textarea id="cors_allowed_headers" name="config[cors_allowed_headers]" class="rexql-cors-textarea" rows="5" placeholder="Content-Type&#10;Authorization&#10;X-API-KEY&#10;X-Public-Key">' . rex_escape($corsHeaders) . '</textarea>'
    . '<small class="rexql-field-hint">' . $addon->i18n('config_cors_allowed_headers_hint') . '<br />' . $addon->i18n('one_per_line') . '</small>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');
$content .= '</fieldset>';


// Ausgabe Formular
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', $addon->i18n('config_cors'));
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$output = '<form action="' . rex_url::currentBackendPage() . '" id="cors" method="post">';
$output .= $fragment->parse('core/page/section.php');
$output .= '<input type="hidden" name="formsubmit" value="cors" />';
$output .= '</form>';

echo $output;
