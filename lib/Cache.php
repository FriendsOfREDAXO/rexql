<?php

namespace FriendsOfRedaxo\RexQL;

use FriendsOfRedaxo\RexQL\Context;
use FriendsOfRedaxo\RexQL\Services\Logger;

use Psr\Cache\CacheItemPoolInterface;

use rex_addon;
use rex_api_exception;
use rex_dir;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Cache
{
  private CacheItemPoolInterface $cache;
  protected bool $cacheActive = true; // Flag to enable/disable caching
  protected string $cacheKey = '';
  /** @var array<string> */
  protected static array $cacheKeys = [];
  protected string $cacheDirectory = '';
  protected bool $debugMode = false;
  protected ?int $globTTL = 0; // Globally set TTL for cache items
  protected const DEFAULT_TTL = 300; // Default TTL for cache items (in seconds)

  public function __construct(Context $context, string $namespace)
  {
    $this->debugMode = $context->get('debugMode', false);
    $this->cacheActive = $context->get('cache', true);
    $this->cacheDirectory = $context->get('cachePath');
    $this->setCacheKey($namespace);
    if (!$this->cacheDirectory) {
      throw new rex_api_exception('rexQL: Cache: Cache directory not set in context!');
    }
    $addon = $context->getAddon();
    $ttl = $addon->getConfig('cache_ttl', self::DEFAULT_TTL);
    $this->globTTL = $this->debugMode ? 300 : $ttl;
    $this->cache = new FilesystemAdapter($namespace, $this->globTTL, $this->cacheDirectory);
  }

  public function setCacheKey(string $key = ''): void
  {
    $cacheKey = md5($key);

    if (isset(self::$cacheKeys[$cacheKey])) {
      Logger::log('rexQL: Cache: Using existing cache key for namespace: ' . $cacheKey);
      $this->cacheKey = self::$cacheKeys[$cacheKey];
    } else {
      $this->cacheKey = $cacheKey;
      self::$cacheKeys[$cacheKey] = $this->cacheKey;
      Logger::log('rexQL: Cache: New cache key generated for namespace: ' . $cacheKey);
    }
  }

  /**
   * @api
   */
  public function getCacheKey(): string
  {
    return $this->cacheKey;
  }

  protected function getKey(string $key): string
  {
    return $this->cacheKey . '_' . $key;
  }

  /**
   * Get an item from the cache by key
   *
   * @api
   * @param string $key The cache key to retrieve
   * @param mixed $item Optional item to return if cache is disabled or item is not found
   * @return mixed The cached item or the provided item if cache is disabled or not found
   */
  public function get(string $key, mixed $item = null): mixed
  {
    if (!$this->cacheActive) {
      Logger::log('rexQL: Cache: Caching is disabled, returning item');
      return $item;
    }
    $cacheItem = $this->cache->getItem($this->getKey($key));
    if ($cacheItem->isHit()) {
      Logger::log('rexQL: Cache: Cache hit! Loading from cache for key: ' . $key);
      $item = $cacheItem->get();
    } else if ($item) {
      Logger::log('rexQL: Cache: Cache miss for key: ' . $key);
      $this->set($key, $item);
    }
    return $item;
  }

  /**
   * Set an item in the cache by key
   *
   * @api
   * @param string $key The cache key to set
   * @param mixed $item The item to cache
   */
  public function set(string $key, mixed $item): void
  {
    if (!$this->cacheActive) {
      Logger::log('rexQL: Cache: Caching is disabled, not saving item for key: ' . $key);
      return;
    }
    $cacheItem = $this->cache->getItem($this->getKey($key));
    $cacheItem->set($item);
    if ($this->globTTL !== null) {
      $cacheItem->expiresAfter($this->globTTL);
    }
    $saved = $this->cache->save($cacheItem);
    if (!$saved) {
      Logger::log('rexQL: Cache: Failed to save item to cache for key: ' . $key);
    } else {
      Logger::log('rexQL: Cache: Item saved to cache successfully for key: ' . $key);
    }
  }

  public static function invalidate(string $dir = ''): void
  {
    // Invalidate the schema cache by deleting the cache directory
    $cacheDirectory = rex_addon::get('rexql')->getCachePath($dir);
    if (is_dir($cacheDirectory)) {
      rex_dir::delete($cacheDirectory, false);
      Logger::log('rexQL: Cache: ' . $dir . ' cache invalidated successfully.');
    } else {
      Logger::log('rexQL: Cache: ' . $dir . ' cache directory does not exist.');
    }
  }
}
