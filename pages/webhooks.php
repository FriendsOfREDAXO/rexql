<?php

/**
 * rexQL Webhooks Management
 */

use FriendsOfRedaxo\RexQL\Utility;

$addon = rex_addon::get('rexql');
$func = rex_request('func', 'string');
$oid = rex_request('oid', 'int');

// Available webhook events
$availableEvents = [
  'ART_ADDED' => 'Article Added',
  'ART_UPDATED' => 'Article Updated',
  'ART_DELETED' => 'Article Deleted',
  'ART_MOVED' => 'Article Moved',
  'ART_STATUS' => 'Article Status Changed',
  'CAT_ADDED' => 'Category Added',
  'CAT_UPDATED' => 'Category Updated',
  'CAT_DELETED' => 'Category Deleted',
  'CAT_MOVED' => 'Category Moved',
  'CAT_STATUS' => 'Category Status Changed',
  'YFORM_DATA_ADDED' => 'YForm Data Added',
  'YFORM_DATA_UPDATED' => 'YForm Data Updated',
  'YFORM_DATA_DELETED' => 'YForm Data Deleted',
  'CACHE_DELETED' => 'Cache Deleted'
];

// Add webhook
if ($func == 'add' && rex_post('save', 'boolean')) {
  try {
    $name = rex_post('name', 'string');
    $url = rex_post('url', 'string');
    $timeout = rex_post('timeout', 'int', 30);
    $retryAttempts = rex_post('retry_attempts', 'int', 3);

    if (empty($name)) {
      throw new Exception('Name is required');
    }
    if (empty($url)) {
      throw new Exception('URL is required');
    }

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new Exception('Please provide a valid URL');
    }

    // Generate secure random secret
    $secret = bin2hex(random_bytes(32)); // 64 character hex string

    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('rexql_webhook'));
    $sql->setValue('name', $name);
    $sql->setValue('url', $url);
    $sql->setValue('secret', $secret);
    $sql->setValue('timeout', $timeout);
    $sql->setValue('retry_attempts', $retryAttempts);
    $sql->setValue('active', 1);
    $sql->setValue('call_count', 0);
    $sql->setValue('created_by', rex::getUser()->getLogin());
    $sql->setValue('createdate', date('Y-m-d H:i:s'));
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));
    $sql->insert();

    echo rex_view::success('Webhook created successfully! You can copy the auto-generated secret from the list below.');
    $func = '';
  } catch (Exception $e) {
    echo rex_view::error($e->getMessage());
  }
}

// Edit webhook
if ($func == 'edit' && rex_post('save', 'boolean') && $oid > 0) {
  try {
    $name = rex_post('name', 'string');
    $url = rex_post('url', 'string');
    $timeout = rex_post('timeout', 'int', 30);
    $retryAttempts = rex_post('retry_attempts', 'int', 3);
    $active = rex_post('active', 'boolean', false);

    if (empty($name)) {
      throw new Exception('Name is required');
    }
    if (empty($url)) {
      throw new Exception('URL is required');
    }

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new Exception('Please provide a valid URL');
    }

    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('rexql_webhook'));
    $sql->setWhere(['id' => $oid]);
    $sql->setValue('name', $name);
    $sql->setValue('url', $url);
    // Don't update secret - keep existing one
    $sql->setValue('timeout', $timeout);
    $sql->setValue('retry_attempts', $retryAttempts);
    $sql->setValue('active', $active);
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));
    $sql->update();

    echo rex_view::success(rex_i18n::msg('rexql_webhook_updated'));
    $func = '';
  } catch (Exception $e) {
    echo rex_view::error($e->getMessage());
  }
}

// Delete webhook
if ($func == 'delete' && $oid > 0) {
  $sql = rex_sql::factory();
  $sql->setTable(rex::getTable('rexql_webhook'));
  $sql->setWhere(['id' => $oid]);
  $sql->delete();

  echo rex_view::success(rex_i18n::msg('rexql_webhook_deleted'));
  $func = '';
}

// Test webhook
if ($func == 'test' && $oid > 0) {
  try {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('rexql_webhook'));
    $sql->setWhere(['id' => $oid]);
    $sql->select();

    if ($sql->getRows() > 0) {
      $webhook = $sql->getArray()[0];

      // Send test webhook
      $payload = [
        'event' => 'TEST',
        'timestamp' => time(),
        'data' => [
          'test' => true,
          'message' => 'This is a test webhook from rexQL'
        ],
        'source' => 'rexql',
        'site_url' => rex::getServer(),
      ];

      $result = \FriendsOfRedaxo\RexQL\Webhook::testWebhook($webhook);

      if ($result['success']) {
        echo rex_view::success('Test webhook sent successfully');
      } else {
        echo rex_view::error('Test webhook failed: ' . $result['message']);
      }
    } else {
      echo rex_view::error('Webhook not found');
    }
  } catch (Exception $e) {
    echo rex_view::error('Test failed: ' . $e->getMessage());
  }
  $func = '';
}

$content = '';

if ($func == 'add' || $func == 'edit') {
  $fragment = new rex_fragment();
  $fragment->setVar('func', $func);
  $fragment->setVar('oid', $oid);
  $content = $fragment->parse('form.webhooks.php');

  // Form buttons
  $formElements = [];
  $n = [];
  $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="1">Save</button>';
  $formElements[] = $n;
  $n = [];
  $n['field'] = '<a class="btn btn-abort" href="' . rex_url::currentBackendPage() . '">Cancel</a>';
  $formElements[] = $n;

  $fragment = new rex_fragment();
  $fragment->setVar('elements', $formElements, false);
  $buttons = $fragment->parse('core/form/submit.php');
  $buttons = '<fieldset class="rex-form-action">' . $buttons . '</fieldset>';

  $fragment = new rex_fragment();
  $fragment->setVar('class', 'edit');
  $fragment->setVar('title', $func == 'add' ? 'Add Webhook' : 'Edit Webhook');
  $fragment->setVar('body', $content, false);
  $fragment->setVar('buttons', $buttons, false);
  $output = $fragment->parse('core/page/section.php');

  $content = '<form action="' . rex_url::currentBackendPage() . '" method="post">' . $output . '</form>';
} else {
  // List webhooks
  $list = rex_list::factory('SELECT id, name, url, secret, active, timeout, retry_attempts, last_called, last_status, call_count, created_by, createdate, updatedate FROM ' . rex::getTable('rexql_webhook') . ' ORDER BY createdate DESC');
  $list->addTableAttribute('class', 'table-striped');

  $list->removeColumn('id');
  $list->setColumnSortable('name');
  $list->setColumnSortable('call_count');
  $list->setColumnSortable('last_called');

  $list->setColumnLabel('name', 'Name');
  $list->setColumnLabel('url', 'URL');
  $list->setColumnLabel('secret', 'Secret');
  $list->setColumnLabel('active', 'Active');
  $list->setColumnLabel('timeout', 'Timeout');
  $list->setColumnLabel('retry_attempts', 'Retries');
  $list->setColumnLabel('last_called', 'Last Called');
  $list->setColumnLabel('last_status', 'Last Status');
  $list->setColumnLabel('call_count', 'Call Count');
  $list->setColumnLabel('created_by', 'Created By');
  $list->setColumnLabel('createdate', 'Created');
  $list->setColumnLabel('updatedate', 'Updated');
  $list->setColumnLabel('functions', 'Actions');

  // Format URL column
  $list->setColumnFormat('url', 'custom', function ($params) {
    $url = $params['list']->getValue('url');
    $domain = parse_url($url, PHP_URL_HOST);
    return '<a href="' . rex_escape($url) . '" target="_blank" title="' . rex_escape($url) . '">' . rex_escape($domain) . '</a>';
  });

  // Format secret column with copy button
  $list->setColumnFormat('secret', 'custom', function ($params) {
    $secret = $params['list']->getValue('secret');
    return '<div class="btn-group" style="display:flex">' .
      '<code>' . substr($secret, 0, 16) . '...</code>' .
      Utility::copyToClipboardButton($secret) .
      '</div>';
  });

  // Format active column
  $list->setColumnFormat('active', 'custom', function ($params) {
    return $params['value'] ? '<span class="rex-online">Active</span>' : '<span class="rex-offline">Inactive</span>';
  });

  // Format last status column
  $list->setColumnFormat('last_status', 'custom', function ($params) {
    $status = $params['list']->getValue('last_status');
    if (empty($status)) {
      return '<span class="text-muted">-</span>';
    }
    $class = strpos($status, 'success') !== false ? 'success' : 'danger';
    return '<span class="label label-' . $class . '">' . rex_escape($status) . '</span>';
  });

  // Format date columns
  foreach (['last_called', 'createdate', 'updatedate'] as $column) {
    $list->setColumnFormat($column, 'custom', function ($params) use ($column) {
      $value = $params['list']->getValue($column);
      if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '<span class="text-muted">-</span>';
      }
      return rex_formatter::intlDateTime($value);
    });
  }

  // Actions column
  $list->addColumn('functions', 'Actions');
  $list->setColumnFormat('functions', 'custom', function ($params) {
    $id = $params['list']->getValue('id');
    $editUrl = rex_url::currentBackendPage(['func' => 'edit', 'oid' => $id]);
    $deleteUrl = rex_url::currentBackendPage(['func' => 'delete', 'oid' => $id]);
    $testUrl = rex_url::currentBackendPage(['func' => 'test', 'oid' => $id]);

    return '<div class="btn-group">' .
      '<a href="' . $editUrl . '" class="btn btn-xs btn-default" title="Edit"><i class="fa fa-edit"></i></a>' .
      '<a href="' . $testUrl . '" class="btn btn-xs btn-info" title="Test Webhook"><i class="fa fa-send"></i></a>' .
      '<a href="' . $deleteUrl . '" class="btn btn-xs btn-danger" onclick="return confirm(\'Delete this webhook?\')" title="Delete"><i class="fa fa-trash"></i></a>' .
      '</div>';
  });

  // Add button
  $content .= '<div class="btn-toolbar"><a href="' . rex_url::currentBackendPage(['func' => 'add']) . '" class="btn btn-primary">Add Webhook</a></div>';
  $content .= $list->get();
}

echo $content;
