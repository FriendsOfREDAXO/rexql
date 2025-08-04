<?php

/**
 * @var rex_fragment $this
 */

$func = $this->getVar('func', 'add');
$oid = $this->getVar('oid', 0);

$data = [
  'name' => '',
  'url' => '',
  'timeout' => 30,
  'retry_attempts' => 3,
  'active' => true,
];

if ($func === 'add') {
?>
  <input type="hidden" name="func" value="add">
  <input type="hidden" name="save" value="1">
  <?php
} elseif ($func == 'edit' && $oid > 0) {
  $sql = rex_sql::factory();
  $sql->setQuery('SELECT * FROM ' . rex::getTable('rexql_webhook') . ' WHERE id = ?', [$oid]);

  if ($sql->getRows() == 0) {
    echo rex_view::error('Webhook not found');
  } else {
    $data['name'] = $sql->getValue('name');
    $data['url'] = $sql->getValue('url');
    $data['timeout'] = $sql->getValue('timeout') ?: 30;
    $data['retry_attempts'] = $sql->getValue('retry_attempts') ?: 3;
    $data['active'] = (bool) $sql->getValue('active');
  ?>
    <input type="hidden" name="func" value="edit">
    <input type="hidden" name="oid" value="<?= $oid ?>">
    <input type="hidden" name="save" value="1">
<?php
  }
}
?>

<fieldset>
  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">Name *</label>
    </dt>
    <dd>
      <input class="form-control" type="text" name="name" value="<?= rex_escape($data['name']) ?>" required>
      <p class="help-block">A descriptive name for this webhook</p>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">URL *</label>
    </dt>
    <dd>
      <input class="form-control" type="url" name="url" value="<?= rex_escape($data['url']) ?>" required>
      <p class="help-block">The endpoint URL where webhook requests will be sent</p>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">Timeout (seconds)</label>
    </dt>
    <dd>
      <input class="form-control" type="number" name="timeout" value="<?= $data['timeout'] ?>" min="1" max="300">
      <p class="help-block">How long to wait for a response before timing out (1-300 seconds)</p>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">Retry Attempts</label>
    </dt>
    <dd>
      <input class="form-control" type="number" name="retry_attempts" value="<?= $data['retry_attempts'] ?>" min="0" max="10">
      <p class="help-block">Number of retry attempts if the webhook fails (0-10)</p>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">Active</label>
    </dt>
    <dd>
      <label>
        <input type="checkbox" name="active" value="1" <?php if ($data['active']) echo 'checked'; ?>>
        Enable this webhook
      </label>
      <p class="help-block">Inactive webhooks will not be called</p>
    </dd>
  </dl>
</fieldset>

<div class="alert alert-info">
  <h4>Webhook Information</h4>
  <p><strong>Automatic Triggering:</strong> This webhook will be automatically triggered for all rexQL cache invalidation events including:</p>
  <ul>
    <li>Articles: Added, Updated, Deleted, Moved, Status Changed</li>
    <li>Categories: Added, Updated, Deleted, Moved, Status Changed</li>
    <li>YForm Data: Added, Updated, Deleted</li>
    <li>System: Cache Deleted</li>
  </ul>

  <p><strong>Auto-generated Secret:</strong> A secure secret key will be automatically generated for this webhook. You can copy it from the webhook list after creation.</p>

  <h5>Webhook Payload Structure</h5>
  <p>When triggered, webhooks will receive a JSON payload with the following structure:</p>
  <pre>{
  "event": "ART_ADDED",
  "timestamp": 1704376800,
  "data": {
    "subject": "Article object or ID",
    "params": {},
    "extension_point": "ART_ADDED",
    "normalized_name": "article-slug",
    "table_name": "rex_article",
    "record_id": 123
  },
  "source": "rexql",
  "site_url": "https://your-site.com"
}</pre>

  <h5>Request Headers</h5>
  <ul>
    <li><code>Content-Type: application/json</code></li>
    <li><code>X-Webhook-Signature: sha256=&lt;signature&gt;</code></li>
    <li><code>X-Webhook-Secret: &lt;auto-generated-secret&gt;</code></li>
    <li><code>User-Agent: REDAXO-rexQL-Webhook/1.0</code></li>
  </ul>
</div>