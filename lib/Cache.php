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
   * Schema aus dem Cache holen oder erstellen
   * 
   * @param callable $generator Funktion die das Schema erstellt
   * @return \GraphQL\Type\Schema
   */
  public static function getSchema(callable $generator)
  {
    // GraphQL Schema caching ist komplex wegen Object-References
    // Für bessere Performance verwenden wir eine einfache statische Variable
    static $cachedSchema = null;
    static $cacheKey = null;

    $currentCacheKey = md5(serialize([
      rex_addon::get('rexql')->getConfig('allowed_tables', []),
      rex_addon::get('rexql')->getConfig('schema_version', 1),
      \rex_clang::getAll(),
    ]));

    // Wenn Cache-Key sich geändert hat oder kein Schema gecacht ist
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

    // ExecutionResult Objekte können nicht direkt gecacht werden wegen Closures
    // Stattdessen cacheen wir das Array-Resultat und erstellen ein neues ExecutionResult
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
   * Wert aus dem Cache holen oder generieren
   *
   * @param string $namespace Cache-Namespace
   * @param string $key Cache-Schlüssel
   * @param callable $generator Funktion die den Wert generiert
   * @param int $ttl Gültigkeit in Sekunden (0 = unbegrenzt)
   * @return mixed
   */
  private static function get(string $namespace, string $key, callable $generator, int $ttl = 0)
  {
    $cachePath = self::getCachePath($namespace, $key);

    // Cache existiert und ist gültig
    if (file_exists($cachePath)) {
      $cacheData = rex_file::getCache($cachePath, null);

      // Wenn Cache-Daten vorhanden sind
      if ($cacheData !== null) {
        // Bei TTL prüfen ob abgelaufen
        if ($ttl > 0 && isset($cacheData['time']) && (time() - $cacheData['time'] > $ttl)) {
          // Cache ist abgelaufen
          rex_file::delete($cachePath);
        } else {
          // Cache ist gültig
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
}
