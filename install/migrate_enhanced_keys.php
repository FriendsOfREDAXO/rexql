<?php

/**
 * Database Migration für erweiterte API-Schlüssel Funktionalität
 */

// Migration für neue Spalten in der API-Keys Tabelle
$sql = rex_sql::factory();

// Prüfen ob die neuen Spalten bereits existieren
$columns = $sql->getArray("SHOW COLUMNS FROM " . rex::getTable('rexql_api_keys'));
$existingColumns = array_column($columns, 'Field');

$newColumns = [
  'public_key' => "ADD COLUMN `public_key` VARCHAR(255) NULL DEFAULT NULL AFTER `api_key`",
  'private_key' => "ADD COLUMN `private_key` VARCHAR(255) NULL DEFAULT NULL AFTER `public_key`",
  'allowed_domains' => "ADD COLUMN `allowed_domains` TEXT NULL DEFAULT NULL AFTER `rate_limit`",
  'allowed_ips' => "ADD COLUMN `allowed_ips` TEXT NULL DEFAULT NULL AFTER `allowed_domains`",
  'https_only' => "ADD COLUMN `https_only` BOOLEAN DEFAULT FALSE AFTER `allowed_ips`",
  'key_type' => "ADD COLUMN `key_type` VARCHAR(50) DEFAULT 'standard' AFTER `https_only`"
];

foreach ($newColumns as $columnName => $alteration) {
  if (!in_array($columnName, $existingColumns)) {
    try {
      $sql->setQuery("ALTER TABLE " . rex::getTable('rexql_api_keys') . " " . $alteration);
      echo "✓ Spalte '$columnName' hinzugefügt\n";
    } catch (rex_sql_exception $e) {
      echo "✗ Fehler beim Hinzufügen der Spalte '$columnName': " . $e->getMessage() . "\n";
    }
  } else {
    echo "ℹ Spalte '$columnName' existiert bereits\n";
  }
}

// Index für Public Key hinzufügen
try {
  $sql->setQuery("CREATE INDEX idx_public_key ON " . rex::getTable('rexql_api_keys') . " (public_key)");
  echo "✓ Index für public_key erstellt\n";
} catch (rex_sql_exception $e) {
  if (!str_contains($e->getMessage(), 'Duplicate key name')) {
    echo "✗ Fehler beim Erstellen des Index: " . $e->getMessage() . "\n";
  } else {
    echo "ℹ Index für public_key existiert bereits\n";
  }
}

echo "Migration abgeschlossen!\n";
