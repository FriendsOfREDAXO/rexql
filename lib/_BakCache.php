<?php

namespace FriendsOfRedaxo\RexQL;

use rex_addon;
use rex_logger;
use rex_dir;
use rex_file;

/**
 * Cache-Verwaltung für rexQL
 */
class _BakCache
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
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Invalidating schema cache', [], __FILE__, __LINE__);
    }

    self::cleanDirectory(self::SCHEMA_CACHE_DIR);

    // Schema-Version erhöhen um In-Memory-Cache zu invalidieren
    $currentVersion = rex_addon::get('rexql')->getConfig('schema_version', 1);
    $newVersion = $currentVersion + 1;
    rex_addon::get('rexql')->setConfig('schema_version', $newVersion);

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Schema version bumped from ' . $currentVersion . ' to ' . $newVersion, [], __FILE__, __LINE__);
    }
  }

  /**
   * Query-Cache invalidieren
   */
  public static function invalidateQueries(): void
  {
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Invalidating query cache', [], __FILE__, __LINE__);
    }

    self::cleanDirectory(self::QUERY_CACHE_DIR);
  }

  /**
   * Kompletten Cache invalidieren
   */
  public static function invalidateAll(): void
  {
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Invalidating all caches', [], __FILE__, __LINE__);
    }

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
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();

    // Generate cache key based on configuration and structure
    $cacheKey = md5(serialize([
      rex_addon::get('rexql')->getConfig('allowed_tables', []),
      rex_addon::get('rexql')->getConfig('schema_version', 1),
      \rex_clang::getAll(),
      // Include relevant addon status to detect structure changes
      self::getAddonStates(),
      // Include YForm table structures if available
      self::getYFormTableStructures(),
    ]));

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Checking schema cache with key: ' . $cacheKey, [], __FILE__, __LINE__);
    }

    // Process-level cache - survives within the same PHP process
    static $processCache = [];
    static $processCacheKey = null;

    // Check if we have this exact schema in our process cache
    if ($processCacheKey === $cacheKey && isset($processCache[$cacheKey])) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Schema found in process cache', [], __FILE__, __LINE__);
      }
      return $processCache[$cacheKey];
    }

    // Check if we have a valid cache file (indicates schema config hasn't changed)
    $cachePath = self::getCachePath(self::SCHEMA_CACHE_DIR, $cacheKey);
    $cacheExists = file_exists($cachePath);

    if ($cacheExists) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Schema cache marker exists, schema config unchanged', [], __FILE__, __LINE__);
      }
    } else {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Schema cache marker missing, schema config changed', [], __FILE__, __LINE__);
      }
    }

    // Always build schema for new processes, but log cache status
    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Building schema for current process', [], __FILE__, __LINE__);
    }

    $schema = $generator();

    // Store in process cache
    $processCache[$cacheKey] = $schema;
    $processCacheKey = $cacheKey;

    // Create/update cache marker file if it doesn't exist
    if (!$cacheExists) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Creating cache marker file', [], __FILE__, __LINE__);
      }
      self::set(self::SCHEMA_CACHE_DIR, $cacheKey, [
        'schema_built' => true,
        'timestamp' => time(),
        'version' => rex_addon::get('rexql')->getConfig('schema_version', 1)
      ]);
    }

    return $schema;
  }

  /**
   * Get query result from cache or generate it
   * 
   * @param string $queryHash Hash of the query
   * @param callable $generator Function that creates the result
   * @param bool $cacheWithErrors Whether to cache results that contain errors (default: true)
   * @return mixed
   */
  public static function getQueryResult(string $queryHash, callable $generator, bool $cacheWithErrors = true)
  {
    // Check if query caching is enabled in config
    $isCachingEnabled = rex_addon::get('rexql')->getConfig('cache_queries', false);
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Query result requested, hash: ' . $queryHash, [], __FILE__, __LINE__);
      $logger->log('debug', 'rexQL Cache: Caching enabled: ' . ($isCachingEnabled ? 'yes' : 'no'), [], __FILE__, __LINE__);
    }

    // Skip cache only if caching is disabled or explicitly bypassed
    if (!$isCachingEnabled || rex_get('noCache', 'bool', false)) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Skipping cache (disabled or bypassed)', [], __FILE__, __LINE__);
      }
      return $generator();
    }

    // Generate cache key with additional context
    $cacheKey = $queryHash;

    // ExecutionResult objects cannot be cached directly due to closures
    // Instead, cache the array result and create a new ExecutionResult
    $cachedArray = self::get(self::QUERY_CACHE_DIR, $cacheKey, function () use ($generator, $cacheWithErrors, $debugMode, $logger) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Query cache miss, generating result', [], __FILE__, __LINE__);
      }

      $result = $generator();

      // Check if we should cache results with errors
      if (!$cacheWithErrors && !empty($result->errors)) {
        if ($debugMode) {
          $logger->log('debug', 'rexQL Cache: Not caching result due to errors (cacheWithErrors=false)', [], __FILE__, __LINE__);
        }
        // Special marker to indicate we shouldn't cache this result
        return ['__nocache' => true, 'result' => $result->toArray()];
      }

      // Check for explicit cache directive in result extensions
      $extensions = $result->extensions ?? [];
      if (isset($extensions['shouldCache']) && $extensions['shouldCache'] === false) {
        if ($debugMode) {
          $logger->log('debug', 'rexQL Cache: Not caching result due to shouldCache=false directive', [], __FILE__, __LINE__);
        }
        // Don't cache results that explicitly request no caching
        return ['__nocache' => true, 'result' => $result->toArray()];
      }

      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Caching query result', [], __FILE__, __LINE__);
      }

      // Only cache the array, not the ExecutionResult object
      return $result->toArray();
    }, 300); // 5 minute cache

    // If we got a no-cache marker, return the original result without caching
    if (is_array($cachedArray) && isset($cachedArray['__nocache']) && $cachedArray['__nocache'] === true) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Returning uncached result', [], __FILE__, __LINE__);
      }
      // This result wasn't cached due to errors, so we regenerate it
      return $generator();
    }

    // If we got an array from the cache, create a new ExecutionResult
    if (is_array($cachedArray)) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Query cache hit, returning cached result', [], __FILE__, __LINE__);
      }

      $result = new \GraphQL\Executor\ExecutionResult(
        $cachedArray['data'] ?? null,
        $cachedArray['errors'] ?? []
      );

      // Add cache status to extensions if in debug mode
      if ($debugMode) {
        $extensions = $cachedArray['extensions'] ?? [];
        $extensions['cached'] = true;
        $extensions['cache_key'] = $cacheKey;
        $result->extensions = $extensions;
      }

      return $result;
    }

    // Fallback: Generate directly if there's a cache problem
    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Cache problem, generating result directly', [], __FILE__, __LINE__);
    }
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
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();
    $cachePath = self::getCachePath($namespace, $key);

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Checking cache file: ' . $cachePath, [], __FILE__, __LINE__);
    }

    // Cache exists and is valid
    if (file_exists($cachePath)) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Cache file exists, reading...', [], __FILE__, __LINE__);
      }

      $cacheData = rex_file::getCache($cachePath, null);

      // If cache data exists
      if ($cacheData !== null) {
        // Check TTL if expired
        if ($ttl > 0 && isset($cacheData['time']) && (time() - $cacheData['time'] > $ttl)) {
          if ($debugMode) {
            $logger->log('debug', 'rexQL Cache: Cache expired, deleting file', [], __FILE__, __LINE__);
          }
          // Cache is expired
          rex_file::delete($cachePath);
        } else {
          if ($debugMode) {
            $logger->log('debug', 'rexQL Cache: Cache hit! Returning cached data', [], __FILE__, __LINE__);
          }
          // Cache is valid
          return $cacheData['data'];
        }
      } else {
        if ($debugMode) {
          $logger->log('debug', 'rexQL Cache: Cache file exists but data is null', [], __FILE__, __LINE__);
        }
      }
    } else {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Cache file does not exist', [], __FILE__, __LINE__);
      }
    }

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Generating new data for namespace: ' . $namespace, [], __FILE__, __LINE__);
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
    $debugMode = rex_addon::get('rexql')->getConfig('debug_mode', false);
    $logger = rex_logger::factory();

    // Cache-Verzeichnis erstellen falls nicht vorhanden
    $cacheDir = self::getCacheDir($namespace);
    if (!is_dir($cacheDir)) {
      if ($debugMode) {
        $logger->log('debug', 'rexQL Cache: Creating cache directory: ' . $cacheDir, [], __FILE__, __LINE__);
      }
      rex_dir::create($cacheDir);
    }

    // Cache schreiben
    $cachePath = self::getCachePath($namespace, $key);

    $cacheData = [
      'time' => time(),
      'data' => $data
    ];

    if ($debugMode) {
      $logger->log('debug', 'rexQL Cache: Writing cache file: ' . $cachePath, [], __FILE__, __LINE__);
    }

    $success = rex_file::putCache($cachePath, $cacheData);

    if ($debugMode) {
      if ($success) {
        $logger->log('debug', 'rexQL Cache: Cache file written successfully', [], __FILE__, __LINE__);
      } else {
        $logger->log('error', 'rexQL Cache: Failed to write cache file: ' . $cachePath, [], __FILE__, __LINE__);
      }
    }
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

  /**
   * Determine if a result should be cached based on its error content
   *
   * @param \GraphQL\Executor\ExecutionResult $result The query result to check
   * @return bool True if the result should be cached, false otherwise
   */
  public static function shouldCacheResult(\GraphQL\Executor\ExecutionResult $result): bool
  {
    // Always cache results without errors
    if (empty($result->errors)) {
      return true;
    }

    // Check for errors that should prevent caching
    foreach ($result->errors as $error) {
      // Don't cache system errors, only cache validation errors
      // System errors might be transient and should be reported live
      if ($error instanceof \GraphQL\Error\Error) {
        $originalError = $error->getPrevious();

        // Don't cache database connection errors
        if ($originalError instanceof \rex_sql_exception) {
          return false;
        }

        // Don't cache permission errors
        if (strpos($error->getMessage(), 'permission') !== false) {
          return false;
        }

        // Don't cache internal server errors
        if (strpos($error->getMessage(), 'internal') !== false) {
          return false;
        }
      }
    }

    // Cache validation errors (syntax, field validation, etc.)
    // These are stable and can be cached safely
    return true;
  }
}
