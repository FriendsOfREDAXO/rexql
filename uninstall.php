<?php

/**
 * Uninstallation of the rexQL Addon
 * 
 * @var rex_addon $this
 */

$tables = [
  'rexql_query_log',
  'rexql_api_keys',
];

foreach ($tables as $table) {
  $tableName = rex::getTable($table);
  rex_sql_table::get($tableName)->drop();
}

// Reset configuration
$this->setConfig([]);

// Delete cache
rex_dir::delete(rex_path::addonCache('rexql'));
