<?php

/**
 * Installation des rexQL Addons
 * 
 * @var rex_addon $this
 */

$error = '';

// Überprüfen ob graphql-php verfügbar ist
if (!class_exists('GraphQL\\GraphQL')) {
  $error = 'Das Paket "webonyx/graphql-php" ist nicht installiert. Führen Sie "composer install" im Addon-Verzeichnis aus.';
}

// Überprüfen ob benötigte Addons verfügbar sind
$requiredAddons = ['yform', 'url', 'yrewrite'];
foreach ($requiredAddons as $addon) {
  if (!rex_addon::get($addon)->isAvailable()) {
    $error .= $error ? '<br>' : '';
    $error .= 'Das Addon "' . $addon . '" ist nicht verfügbar, aber für rexQL erforderlich.';
  }
}

// Datenbankstruktur erstellen
if (!$error) {
  // API Keys Tabelle
  rex_sql_table::get(rex::getTable('rexql_api_keys'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('api_key', 'varchar(64)', false))
    ->ensureColumn(new rex_sql_column('permissions', 'text', false))
    ->ensureColumn(new rex_sql_column('rate_limit', 'int(10) unsigned', false, 100))
    ->ensureColumn(new rex_sql_column('last_used', 'datetime'))
    ->ensureColumn(new rex_sql_column('usage_count', 'int(10) unsigned', false, 0))
    ->ensureColumn(new rex_sql_column('active', 'tinyint(1)', false, 1))
    ->ensureColumn(new rex_sql_column('created_by', 'varchar(255)', false))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime', false))
    ->ensureColumn(new rex_sql_column('updatedate', 'datetime', false))
    // Public/Private Key Felder
    ->ensureColumn(new rex_sql_column('public_key', 'varchar(64)'))
    ->ensureColumn(new rex_sql_column('private_key', 'varchar(64)'))
    // Domain/IP Restriction Felder
    ->ensureColumn(new rex_sql_column('allowed_domains', 'text'))
    ->ensureColumn(new rex_sql_column('allowed_ips', 'text'))
    ->ensureColumn(new rex_sql_column('https_only', 'tinyint(1)', false, 0))
    ->ensureColumn(new rex_sql_column('key_type', 'varchar(50)', false, 'standard'))
    ->ensureIndex(new rex_sql_index('api_key', ['api_key'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('active', ['active']))
    ->ensure();

  // Query Logs Tabelle
  rex_sql_table::get(rex::getTable('rexql_query_log'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('api_key_id', 'int(10) unsigned', true)) // nullable for SET_NULL foreign key
    ->ensureColumn(new rex_sql_column('query', 'text', false))
    ->ensureColumn(new rex_sql_column('variables', 'text', true)) // nullable
    ->ensureColumn(new rex_sql_column('execution_time', 'decimal(8,3)', false))
    ->ensureColumn(new rex_sql_column('memory_usage', 'int(10) unsigned', false))
    ->ensureColumn(new rex_sql_column('success', 'tinyint(1)', false))
    ->ensureColumn(new rex_sql_column('error_message', 'text', true)) // nullable
    ->ensureColumn(new rex_sql_column('ip_address', 'varchar(45)', false))
    ->ensureColumn(new rex_sql_column('user_agent', 'text', true)) // nullable
    ->ensureColumn(new rex_sql_column('createdate', 'datetime', false))
    ->ensureIndex(new rex_sql_index('api_key_id', ['api_key_id']))
    ->ensureIndex(new rex_sql_index('createdate', ['createdate']))
    ->ensureIndex(new rex_sql_index('success', ['success']))
    ->ensureForeignKey(new rex_sql_foreign_key('fk_rexql_query_log_api_key', rex::getTable('rexql_api_keys'), ['api_key_id' => 'id'], rex_sql_foreign_key::SET_NULL))
    ->ensure();
}

if ($error) {
  throw new rex_functional_exception($error);
}
