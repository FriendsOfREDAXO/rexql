<?php

/**
 * rexQL API-Schlüssel Verwaltung
 */

use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\Utility;

$addon = rex_addon::get('rexql');
$func = rex_request('func', 'string');
$oid = rex_request('oid', 'int');

// API-Schlüssel hinzufügen
if ($func == 'add' && rex_post('save', 'boolean')) {
  try {
    $name = rex_post('name', 'string');
    $permissions = rex_post('permissions', 'array', []);
    $rateLimit = rex_post('rate_limit', 'int', 100);
    $keyType = rex_post('key_type', 'string', 'standard');

    // Domain/IP restrictions (independent of key type)
    $enableDomainRestrictions = rex_post('enable_domain_restrictions', 'boolean', false);
    $allowedDomains = $enableDomainRestrictions ? array_filter(explode("\n", rex_post('allowed_domains', 'string', ''))) : [];
    $allowedIps = $enableDomainRestrictions ? array_filter(explode("\n", rex_post('allowed_ips', 'string', ''))) : [];
    $httpsOnly = rex_post('https_only', 'boolean', false);

    if (empty($name)) {
      throw new Exception('Name ist erforderlich');
    }

    if ($keyType === 'public_private') {
      $apiKey = ApiKey::createPublicPrivateKey($name, $permissions, $rateLimit, $allowedDomains, $allowedIps, $httpsOnly);
      echo rex_view::success($addon->i18n('public_private_key_created') . ':<br>' .
        '<strong>Public Key:</strong> <code>' . $apiKey->getPublicKey() . '</code><br>' .
        '<strong>Private Key:</strong> <code>' . $apiKey->getPrivateKey() . '</code>');
    } else {
      // Standard key - create it first, then apply restrictions
      $apiKey = ApiKey::create($name, $permissions, $rateLimit);

      // Apply restrictions if enabled
      if ($enableDomainRestrictions || $httpsOnly) {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('rexql_api_keys'));
        $sql->setWhere(['id' => $apiKey->getId()]);
        $sql->setValue('allowed_domains', json_encode($allowedDomains));
        $sql->setValue('allowed_ips', json_encode($allowedIps));
        $sql->setValue('https_only', $httpsOnly);
        $sql->update();
      }

      echo rex_view::success($addon->i18n('api_key_created') . ': <code>' . $apiKey->getApiKey() . '</code>');
    }

    $func = '';
  } catch (Exception $e) {
    echo rex_view::error($e->getMessage());
  }
}

// API-Schlüssel bearbeiten
if ($func == 'edit' && rex_post('save', 'boolean') && $oid > 0) {
  try {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('rexql_api_keys'));
    $sql->setWhere(['id' => $oid]);

    $name = rex_post('name', 'string');
    $permissions = rex_post('permissions', 'array', []);
    $rateLimit = rex_post('rate_limit', 'int', 100);
    $active = rex_post('active', 'boolean', false);

    // Domain/IP restrictions (independent of key type)
    $enableDomainRestrictions = rex_post('enable_domain_restrictions', 'boolean', false);
    $allowedDomains = $enableDomainRestrictions ? array_filter(explode("\n", rex_post('allowed_domains', 'string', ''))) : [];
    $allowedIps = $enableDomainRestrictions ? array_filter(explode("\n", rex_post('allowed_ips', 'string', ''))) : [];
    $httpsOnly = rex_post('https_only', 'boolean', false);

    if (empty($name)) {
      throw new Exception('Name ist erforderlich');
    }

    $sql->setValue('name', $name);
    $sql->setValue('permissions', json_encode($permissions));
    $sql->setValue('rate_limit', $rateLimit);
    $sql->setValue('active', $active);
    $sql->setValue('allowed_domains', json_encode($allowedDomains));
    $sql->setValue('allowed_ips', json_encode($allowedIps));
    $sql->setValue('https_only', $httpsOnly);
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));
    $sql->update();

    echo rex_view::success($addon->i18n('api_key_updated'));
    $func = '';
  } catch (Exception $e) {
    echo rex_view::error($e->getMessage());
  }
}

// API-Schlüssel löschen
if ($func == 'delete' && $oid > 0) {
  $sql = rex_sql::factory();
  $sql->setTable(rex::getTable('rexql_api_keys'));
  $sql->setWhere(['id' => $oid]);
  $sql->delete();

  echo rex_view::success($addon->i18n('api_key_deleted'));
  $func = '';
}

$content = '';


if ($func == 'add' || $func == 'edit') {

  $fragment = new rex_fragment();
  $fragment->setVar('addon', $addon);
  $fragment->setVar('func', $func);
  $fragment->setVar('oid', $oid);
  $content = $fragment->parse('form.permissions.php');

  // Formular für neuen API-Schlüssel


  $formElements = [];
  $n = [];
  $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $addon->i18n('save') . '">' . $addon->i18n('save') . '</button>';
  $formElements[] = $n;
  $n = [];
  $n['field'] = '<a class="btn btn-abort" href="' . rex_url::currentBackendPage() . '">' . $addon->i18n('cancel') . '</a>';
  $formElements[] = $n;
  $fragment = new rex_fragment();
  $fragment->setVar('elements', $formElements, false);
  $buttons = $fragment->parse('core/form/submit.php');
  $buttons = '<fieldset class="rex-form-action">
    ' . $buttons . '
  </fieldset>';
  $fragment = new rex_fragment();
  $fragment->setVar('class', 'edit');
  $fragment->setVar('title', $addon->i18n('rexql_permissions_add_key'));
  $fragment->setVar('body', $content, false);
  $fragment->setVar('buttons', $buttons, false);
  $output = $fragment->parse('core/page/section.php');

  $content = '<form action="' . rex_url::currentBackendPage() . '" method="post">
    ' . $output . '
  </form>
  ';
} else {
  // Liste der API-Schlüssel
  $list = rex_list::factory('SELECT id, name, api_key, public_key, private_key, https_only, key_type, permissions, usage_count, last_used, active FROM ' . rex::getTable('rexql_api_keys') . ' ORDER BY createdate DESC');
  $list->addTableAttribute('class', 'table-striped');

  // Spalten definieren
  $list->removeColumn('id');

  $list->setColumnSortable('name');
  $list->setColumnSortable('usage_count');

  $list->setColumnLabel('name', $addon->i18n('permissions_name'));
  $list->setColumnLabel('api_key', $addon->i18n('permissions_api_key'));
  $list->setColumnLabel('permissions', $addon->i18n('permissions_permissions'));
  $list->setColumnLabel('usage_count', $addon->i18n('permissions_usage_count'));
  // $list->setColumnLabel('rate_limit', $addon->i18n('permissions_rate_limit'));
  $list->setColumnLabel('last_used', $addon->i18n('permissions_last_used'));
  $list->setColumnLabel('active', $addon->i18n('permissions_active'));
  $list->setColumnLabel('public_key', 'Public Key');
  $list->setColumnLabel('private_key', 'Private Key');
  $list->setColumnLabel('allowed_domains', 'Domain Whitelist');
  $list->setColumnLabel('allowed_ips', 'IP Whitelist');
  $list->setColumnLabel('https_only', 'HTTPS');
  $list->setColumnLabel('key_type', $addon->i18n('permissions_type'));
  // $list->setColumnLabel('createdate', $addon->i18n('permissions_created'));
  $list->setColumnLabel('updatedate', $addon->i18n('permissions_updated'));
  $list->setColumnLabel('created_by', $addon->i18n('permissions_created_by'));
  $list->setColumnLabel('functions', ' ');

  // API-Schlüssel (teilweise anzeigen)
  $list->setColumnFormat('api_key', 'custom', function ($params) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $value = $list->getValue('api_key');
    return  '<div class="btn-group" style="display:flex">' .
      '<code>' . substr($value, 0, 16) . '...</code>' .
      Utility::copyToClipboardButton($value) .
      '</div>';
  });

  $list->setColumnFormat('permissions', 'custom', function ($params) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $value = json_decode($list->getValue('permissions'), true) ?: [];
    if (in_array('read:all', $value)) {
      $value = array_filter($value, fn($perm) => $perm === 'read:all');
    }
    $permissionsLabel = '';

    if (empty($value)) {
      $permissionsLabel = '-';
    } else {
      $values = array_map(function ($perm) {
        return '<span class="label label-info">' . $perm . '</span>';
      }, $value);
      $permissionsLabel = implode('', $values);
    }

    return
      '<div class="btn-group" style="display:flex; flex-wrap: wrap; gap: 4px">' .
      $permissionsLabel
      . '</div>';
  });


  $list->setColumnFormat('public_key', 'custom', function ($params) use ($addon) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $value = $list->getValue('public_key');
    if (empty($value)) {
      return '<span class="label label-warning">' . $addon->i18n('permissions_not_used') . '</span>';
    }
    return '<div class="btn-group" style="display:flex">' .
      '<code>' . substr($value, 0, 16) . '...</code>' .
      Utility::copyToClipboardButton($value) .
      '</div>';
  });

  $list->setColumnFormat('private_key', 'custom', function ($params) use ($addon) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $value = $list->getValue('private_key');
    if (empty($value)) {
      return '<span class="label label-warning">' . $addon->i18n('permissions_not_used') . '</span>';
    }
    return '<div class="btn-group" style="display:flex">' .
      '<code>' . substr($value, 0, 16) . '...</code>' .
      Utility::copyToClipboardButton($value) .
      '</div>';
  });

  $list->setColumnFormat('https_only', 'custom', function ($params) {
    $httpsOnly = $params['value'] ? '<span class="label label-success"><i class="fa fa-check"></i></span>' : '<span class="label label-danger"><i class="fa fa-ban"></i></span>';
    return '<div style="margin: 0 auto; max-width: max-content">' . $httpsOnly . '</div>';
  });

  // Format domain and IP restrictions
  $list->setColumnFormat('allowed_domains', 'custom', function ($params) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $value = $list->getValue('allowed_domains');
    if (empty($value)) {
      return '<span class="text-muted">-</span>';
    }
    $domains = json_decode($value, true);
    if (empty($domains)) {
      return '<span class="text-muted">-</span>';
    }
    return '<small>' . implode('<br>', array_slice($domains, 0, 3)) . (count($domains) > 3 ? '<br>+' . (count($domains) - 3) . ' more' : '') . '</small>';
  });

  $list->setColumnFormat('allowed_ips', 'custom', function ($params) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $value = $list->getValue('allowed_ips');
    if (empty($value)) {
      return '<span class="text-muted">-</span>';
    }
    $ips = json_decode($value, true);
    if (empty($ips)) {
      return '<span class="text-muted">-</span>';
    }
    return '<small>' . implode('<br>', array_slice($ips, 0, 3)) . (count($ips) > 3 ? '<br>+' . (count($ips) - 3) . ' more' : '') . '</small>';
  });

  // Status formatieren
  $list->setColumnFormat('active', 'custom', function ($params) use ($addon) {
    return $params['value'] ? '<span class="rex-online">' . $addon->i18n('permissions_active') . '</span>' : '<span class="rex-offline">' . $addon->i18n('permissions_inactive') . '</span>';
  });

  // Datum formatieren
  foreach (['last_used', 'createdate', 'updatedate'] as $column) {
    $list->setColumnFormat($column, 'custom', function ($params) use ($column) {
      /** @var \rex_list $list */
      $list = $params['list'];
      $value = $list->getValue($column);
      if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '<span class="text-muted">-</span>';
      }
      return rex_formatter::intlDateTime($value);
    });
  }

  $list->setColumnFormat('key_type', 'custom', function ($params) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $keyType = ucwords($list->getValue('key_type'));
    $values = explode('_', $keyType);
    return '<span class="label label-info">' . ucwords(implode(' ', $values)) . '</span>';
  });

  // Funktionen
  $list->addColumn('functions', $addon->i18n('permissions_functions'));
  $list->setColumnFormat('functions', 'custom', function ($params) use ($addon) {
    /** @var \rex_list $list */
    $list = $params['list'];
    $editUrl = rex_url::currentBackendPage(['func' => 'edit', 'oid' => $list->getValue('id')]);
    $deleteUrl = rex_url::currentBackendPage(['func' => 'delete', 'oid' => $list->getValue('id')]);
    return '<div class="btn-group" style="display:flex"><a href="' . $editUrl . '" class="btn btn-xs btn-default" title="' . rex_i18n::msg('edit') . '"><i class="fa fa-edit"></i></a> ' .
      '<a href="' . $deleteUrl . '" class="btn btn-xs btn-danger" onclick="return confirm(\'' . rex_i18n::msg('delete') . ' - ' . $addon->i18n('permissions_delete_confirm') . '\')" title="' . rex_i18n::msg('delete') . '"><i class="fa fa-trash"></i></a></div>';
  });

  // Add-Button oberhalb der Liste
  $content .= '<div class="btn-toolbar"><a href="' . rex_url::currentBackendPage(['func' => 'add']) . '" class="btn btn-primary">' . $addon->i18n('permissions_add') . '</a></div>';

  $content .= $list->get();
}

echo $content;
