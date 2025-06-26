<?php

/**
 * Update-Script für rexQL Addon
 * 
 * @var rex_addon $this
 */

$addon = rex_addon::get('rexql');
$addon->includeFile(__DIR__ . '/install.php');

rex_package_manager::synchronizeWithFileSystem();
