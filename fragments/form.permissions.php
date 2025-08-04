<?php

use \FriendsOfRedaxo\RexQL\RexQL;

/**
 * @var rex_fragment $this
 * @var rex_addon_interface $addon
 */


$addon = $this->getVar('addon', null);
$func = $this->getVar('func', 'add');
$oid = $this->getVar('oid', 0);

/** @var RexQL $api */
$api = rex::getProperty('rexql_api', null);
$queryTypes = $api->getCustomTypes();

// Filter out built-in scalar types if you only want custom types


$data = [
  'name' => '',
  'key_type' => 'standard',
  'permissions' => [],
  'rate_limit' => 100,
  'allowed_domains' => '',
  'allowed_ips' => '',
  'https_only' => false,
];

// Permissions array
$permissions = [
  'read:all' => 'Alle Daten lesen',
];
foreach ($queryTypes as $type) {
  $permissions['read:' . $type] = '<code>' . ucfirst($type) . '</code> lesen';
}


if ($func === 'add') {
?>
  <input type="hidden" name="func" value="add">
  <input type="hidden" name="save" value="1">
  <?php
} else if ($func == 'edit' && $oid > 0) {

  $sql = rex_sql::factory();
  $sql->setQuery('SELECT * FROM ' . rex::getTable('rexql_api_keys') . ' WHERE id = ?', [$oid]);

  if ($sql->getRows() == 0) {
    echo rex_view::error('API-Schlüssel nicht gefunden');
  } else {

    $data['name'] = $sql->getValue('name');
    $data['key_type'] = $sql->getValue('key_type') ?: 'standard';
    $data['rate_limit'] = $sql->getValue('rate_limit') ?: 100;
    $data['https_only'] = (bool) $sql->getValue('https_only');
    $data['active'] = (bool) $sql->getValue('active');
    $data['public_key'] = $sql->getValue('public_key') ?: '';
    $data['private_key'] = $sql->getValue('private_key') ?: '';
    $permissionsJson = $sql->getValue('permissions') ?: '[]';
    $data['permissions'] = json_decode($permissionsJson, true) ?: [];

    // Load domain/IP restrictions from JSON fields
    $allowedDomainsJson = $sql->getValue('allowed_domains');
    $allowedIpsJson = $sql->getValue('allowed_ips');
    $data['allowed_domains'] = $allowedDomainsJson ? implode("\n", json_decode($allowedDomainsJson, true)) : '';
    $data['allowed_ips'] = $allowedIpsJson ? implode("\n", json_decode($allowedIpsJson, true)) : '';
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
      <label class="control-label"><?= $addon->i18n('permissions_active') ?></label>
    </dt>
    <dd>
      <label>
        <input type="checkbox" name="active" value="1" <?php if (isset($data['active']) && $data['active']) echo 'checked'; ?>>
        <?= $addon->i18n('permissions_active') ?>
      </label>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label"><?= $addon->i18n('permissions_name') ?> *</label>
    </dt>
    <dd>
      <input class="form-control" type="text" name="name" value="<?= $data['name'] ?>" required>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">API-Schlüssel Typ</label>
    </dt>
    <dd>
      <div class="radio">
        <label>
          <input type="radio" name="key_type" value="standard" <?php if ($data['key_type'] === 'standard') echo 'checked'; ?>>
          <strong>Standard API-Schlüssel</strong><br>
          <small class="text-muted">Klassischer API-Schlüssel für Server-zu-Server Kommunikation</small>
        </label>
      </div>
      <div class="radio">
        <label>
          <input type="radio" name="key_type" value="public_private" <?php if ($data['key_type'] === 'public_private') echo 'checked'; ?>>
          <strong>Public/Private Key Pair</strong> (Empfohlen für Frontend)<br>
          <small class="text-muted">Sicherer Proxy-Modus mit öffentlichem und privatem Schlüssel</small>
        </label>
      </div>
    </dd>
  </dl>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label"><?= $addon->i18n('permissions_rate_limit') ?></label>
    </dt>
    <dd>
      <input class="form-control rexql-config-input" type="number" name="rate_limit" value="<?= $data['rate_limit'] ?>" min="1" max="10000">
      <small class="rexql-field-hint"><?= $addon->i18n('permissions_rate_limit_hint') ?></small>
    </dd>
  </dl>


  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label">Sicherheitsbeschränkungen</label>
    </dt>
    <dd>
      <div class="checkbox">
        <label>
          <input type="checkbox" id="enable_domain_restrictions" name="enable_domain_restrictions" value="1" <?php if (!empty($data['allowed_domains']) || !empty($data['allowed_ips'])) echo 'checked'; ?>>
          <strong>Domain/IP-Beschränkungen aktivieren</strong><br>
          <small class="text-muted">Beschränkt die Nutzung des API-Schlüssels auf bestimmte Domains oder IP-Adressen</small>
        </label>
      </div>
      <div class="checkbox">
        <label>
          <input type="checkbox" name="https_only" value="1" <?php if ($data['https_only']) echo 'checked'; ?>>
          <strong>Nur HTTPS-Verbindungen erlauben</strong><br>
          <small class="text-muted">API-Schlüssel funktioniert nur über verschlüsselte HTTPS-Verbindungen</small>
        </label>
      </div>
    </dd>
  </dl>

  <dl class="form-group rex-form-group domain-restrictions" style="<?= (!empty($data['allowed_domains']) || !empty($data['allowed_ips'])) ? 'display: block;' : 'display: none;' ?>">
    <dt>
      <label class="control-label">Domain/IP-Beschränkungen</label>
    </dt>
    <dd>
      <dl class="form-group rex-form-group">
        <dt>
          <label>Erlaubte Domains (eine pro Zeile):</label>
        </dt>
        <dd>
          <textarea class="form-control" name="allowed_domains" rows="3" placeholder="https://ihre-domain.de&#10;https://app.ihre-domain.de"><?= htmlspecialchars($data['allowed_domains']) ?></textarea>
          <small class="help-block">Geben Sie vollständige URLs mit Protokoll ein. Leer lassen für keine Domain-Beschränkung.</small>
        </dd>
      </dl>
      <dl class="form-group rex-form-group">
        <dt>
          <label>Erlaubte IP-Adressen (eine pro Zeile):</label>
        </dt>
        <dd>
          <textarea class="form-control" name="allowed_ips" rows="3" placeholder="192.168.1.100&#10;10.0.0.5"><?= htmlspecialchars($data['allowed_ips']) ?></textarea>
          <small class="help-block">IPv4 oder IPv6 Adressen. Leer lassen für keine IP-Beschränkung.</small>
        </dd>
      </dl>
    </dd>
  </dl>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const enableDomainRestrictions = document.getElementById("enable_domain_restrictions");
      const domainRestrictions = document.querySelector(".domain-restrictions");

      function toggleDomainRestrictions() {
        if (enableDomainRestrictions.checked) {
          domainRestrictions.style.display = "block";
        } else {
          domainRestrictions.style.display = "none";
          // Clear values when disabled
          document.querySelector('textarea[name="allowed_domains"]').value = '';
          document.querySelector('textarea[name="allowed_ips"]').value = '';
        }
      }

      enableDomainRestrictions.addEventListener("change", toggleDomainRestrictions);

      // Initialize on page load
      toggleDomainRestrictions();
    });
  </script>

  <dl class="form-group rex-form-group">
    <dt>
      <label class="control-label"><?= $addon->i18n('permissions_permissions') ?></label>
    </dt>
    <dd>
      <?php
      foreach ($permissions as $perm => $label) {
      ?>
        <div class="checkbox">
          <label>
            <input type="checkbox" name="permissions[]" value="<?= $perm ?>" <?php if (in_array($perm, $data['permissions'])) echo 'checked'; ?>>
            <?= $label ?>
          </label>
        </div>
      <?php
      }
      ?>
    </dd>
  </dl>

</fieldset>