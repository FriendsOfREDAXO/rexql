<?php

namespace FriendsOfRedaxo\RexQL;

use rex_addon;
use rex_path;
use rex_dir;
use rex_file;

/**
 * Cache-Verwaltung für rexQL
 */
class Cache
{
  /**
   * Cache-Verzeichnis für GraphQL Schema
   */
  private const SCHEMA_CACHE_DIR = 'rexql_schema';

  /**
   * Cache-Verzeichnis für Query-Resultate
   */
  private const QUERY_CACHE_DIR = 'rexql_queries';

  /**
   * Schema-Cache invalidieren
   */
  public static function invalidateSchema(): void
  {
    self::cleanDirectory(self::SCHEMA_CACHE_DIR);

    // Schema-Version erhöhen um In-Memory-Cache zu invalidieren
    $currentVersion = rex_addon::get('rexql')->getConfig('schema_version', 1);
    rex_addon::get('rexql')->setConfig('schema_version', $currentVersion + 1);
  }

  /**
   * Query-Cache invalidieren
   */
  public static function invalidateQueries(): void
  {
    self::cleanDirectory(self::QUERY_CACHE_DIR);
  }

  /**
   * Kompletten Cache invalidieren
   */
  public static function invalidateAll(): void
  {
    self::invalidateSchema();
    self::invalidateQueries();
  }

  /**
   * Get schema from cache or create it
   * 
   * @param callable $generator Function that creates the schema
   * @return \GraphQL\Type\Schema
   */
  public static function getSchema(callable $generator)
  {
    // GraphQL schema caching is complex due to object references
    // For better performance we use a simple static variable
    static $cachedSchema = null;
    static $cacheKey = null;

    $currentCacheKey = md5(serialize([
      rex_addon::get('rexql')->getConfig('allowed_tables', []),
      rex_addon::get('rexql')->getConfig('schema_version', 1),
      \rex_clang::getAll(),
      // Include relevant addon status to detect structure changes
      self::getAddonStates(),
      // Include YForm table structures if available
      self::getYFormTableStructures(),
    ]));

    // If cache key has changed or no schema is cached
    if ($cacheKey !== $currentCacheKey || $cachedSchema === null) {
      $cachedSchema = $generator();
      $cacheKey = $currentCacheKey;
    }

    return $cachedSchema;
  }

  /**
   * Query-Resultat aus dem Cache holen
   * 
   * @param string $queryHash Hash der Query
   * @param callable $generator Funktion die das Resultat erstellt
   * @return mixed
   */
  public static function getQueryResult(string $queryHash, callable $generator)
  {
    if (!rex_addon::get('rexql')->getConfig('cache_queries', false)) {
      return $generator();
    }

    // ExecutionResult objects cannot be cached directly due to closures
    // Instead, we cache the array result and create a new ExecutionResult
    $cachedArray = self::get(self::QUERY_CACHE_DIR, $queryHash, function () use ($generator) {
      $result = $generator();
      // Nur das Array cacheen, nicht das ExecutionResult Objekt
      return $result->toArray();
    }, 300); // 5 Minuten Cache

    // Wenn wir ein Array aus dem Cache bekommen, erstellen wir ein neues ExecutionResult
    if (is_array($cachedArray)) {
      return new \GraphQL\Executor\ExecutionResult(
        $cachedArray['data'] ?? null,
        $cachedArray['errors'] ?? []
      );
    }

    // Fallback: Direkt generieren falls Cache-Problem
    return $generator();
  }

  /**
   * Get value from cache or generate it
   *
   * @param string $namespace Cache namespace
   * @param string $key Cache key
   * @param callable $generator Function that generates the value
   * @param int $ttl Validity in seconds (0 = unlimited)
   * @return mixed
   */
  private static function get(string $namespace, string $key, callable $generator, int $ttl = 0)
  {
    $cachePath = self::getCachePath($namespace, $key);

    // Cache exists and is valid
    if (file_exists($cachePath)) {
      $cacheData = rex_file::getCache($cachePath, null);

      // If cache data exists
      if ($cacheData !== null) {
        // Check TTL if expired
        if ($ttl > 0 && isset($cacheData['time']) && (time() - $cacheData['time'] > $ttl)) {
          // Cache is expired
          rex_file::delete($cachePath);
        } else {
          // Cache is valid
          return $cacheData['data'];
        }
      }
    }

    // Cache generieren
    $data = $generator();

    // In Cache speichern
    self::set($namespace, $key, $data);

    return $data;
  }

  /**
   * Wert in den Cache schreiben
   *
   * @param string $namespace Cache-Namespace
   * @param string $key Cache-Schlüssel
   * @param mixed $data Daten
   */
  private static function set(string $namespace, string $key, $data): void
  {
    // Cache-Verzeichnis erstellen falls nicht vorhanden
    $cacheDir = self::getCacheDir($namespace);
    if (!is_dir($cacheDir)) {
      rex_dir::create($cacheDir);
    }

    // Cache schreiben
    $cachePath = self::getCachePath($namespace, $key);

    $cacheData = [
      'time' => time(),
      'data' => $data
    ];

    rex_file::putCache($cachePath, $cacheData);
  }

  /**
   * Cache-Verzeichnis leeren
   *
   * @param string $namespace Cache-Namespace
   */
  private static function cleanDirectory(string $namespace): void
  {
    $cacheDir = self::getCacheDir($namespace);
    if (is_dir($cacheDir)) {
      rex_dir::delete($cacheDir);
    }
  }

  /**
   * Cache-Verzeichnis-Pfad ermitteln
   *
   * @param string $namespace Cache-Namespace
   * @return string
   */
  private static function getCacheDir(string $namespace): string
  {
    return rex_addon::get('rexql')->getCachePath($namespace);
  }

  /**
   * Cache-Datei-Pfad ermitteln
   *
   * @param string $namespace Cache-Namespace
   * @param string $key Cache-Schlüssel
   * @return string
   */
  private static function getCachePath(string $namespace, string $key): string
  {
    return self::getCacheDir($namespace) . '/' . $key . '.cache';
  }

  /**
   * Schema-Version manuell setzen (für Entwicklung/Debugging)
   *
   * @param int $version Neue Schema-Version
   */
  public static function setSchemaVersion(int $version): void
  {
    rex_addon::get('rexql')->setConfig('schema_version', $version);
  }

  /**
   * Aktuelle Schema-Version abrufen
   *
   * @return int
   */
  public static function getSchemaVersion(): int
  {
    return rex_addon::get('rexql')->getConfig('schema_version', 1);
  }

  /**
   * Cache-Status abrufen
   *
   * @return array
   */
  public static function getStatus(): array
  {
    $schemaDir = self::getCacheDir(self::SCHEMA_CACHE_DIR);
    $queryDir = self::getCacheDir(self::QUERY_CACHE_DIR);

    $schemaCacheFiles = is_dir($schemaDir) ? count(glob($schemaDir . '/*.cache')) : 0;
    $queryCacheFiles = is_dir($queryDir) ? count(glob($queryDir . '/*.cache')) : 0;

    return [
      'schema_version' => self::getSchemaVersion(),
      'query_caching_enabled' => rex_addon::get('rexql')->getConfig('cache_queries', false),
      'schema_cache_files' => $schemaCacheFiles,
      'query_cache_files' => $queryCacheFiles,
      'schema_cache_dir' => $schemaDir,
      'query_cache_dir' => $queryDir
    ];
  }

  /**
   * Status relevanter Addons für Cache-Key ermitteln
   */
  private static function getAddonStates(): array
  {
    $relevantAddons = ['yform', 'url', 'yrewrite', 'structure'];
    $states = [];

    foreach ($relevantAddons as $addonKey) {
      $addon = rex_addon::get($addonKey);
      $states[$addonKey] = [
        'available' => $addon->isAvailable(),
        'version' => $addon->getVersion()
      ];
    }

    return $states;
  }

  /**
   * YForm Tabellen-Strukturen für Cache-Key ermitteln
   */
  private static function getYFormTableStructures(): array
  {
    if (!rex_addon::get('yform')->isAvailable()) {
      return [];
    }

    $structures = [];
    $allowedTables = rex_addon::get('rexql')->getConfig('allowed_tables', []);

    try {
      $yformTables = \rex_yform_manager_table::getAll();
      foreach ($yformTables as $table) {
        $tableName = $table->getTableName();
        if (in_array($tableName, $allowedTables)) {
          $structures[$tableName] = [
            'name' => $table->getName(),
            'fields' => array_map(function ($field) {
              return [
                'name' => $field->getName(),
                'type' => $field->getTypeName(),
                'label' => $field->getLabel()
              ];
            }, $table->getFields())
          ];
        }
      }
    } catch (\Exception $e) {
      // Fall back to empty array if YForm tables can't be read
      return [];
    }

    return $structures;
  }
}
