<?php

class DataLoader
{
  private $batchLoadFn;
  private $cache = [];
  private $batch = [];
  private $scheduled = false;

  public function __construct(callable $batchLoadFn)
  {
    $this->batchLoadFn = $batchLoadFn;
  }

  public function load($key)
  {
    // Return cached result if available
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }

    // Add to batch if not already there
    if (!isset($this->batch[$key])) {
      $this->batch[$key] = true;
    }

    // Schedule batch execution if not already scheduled
    if (!$this->scheduled) {
      $this->scheduled = true;
      $this->executeBatch();
    }

    return $this->cache[$key] ?? null;
  }

  public function loadMany(array $keys)
  {
    $results = [];
    foreach ($keys as $key) {
      $results[$key] = $this->load($key);
    }
    return $results;
  }

  private function executeBatch()
  {
    if (empty($this->batch)) {
      return;
    }

    $keys = array_keys($this->batch);
    $batchLoadFn = $this->batchLoadFn;

    try {
      $results = $batchLoadFn($keys);

      // Cache results
      foreach ($keys as $key) {
        $this->cache[$key] = $results[$key] ?? null;
      }
    } catch (Exception $e) {
      // Cache error for all keys in batch
      foreach ($keys as $key) {
        $this->cache[$key] = $e;
      }
    }

    // Clear batch
    $this->batch = [];
    $this->scheduled = false;
  }

  public function clear($key = null)
  {
    if ($key === null) {
      $this->cache = [];
    } else {
      unset($this->cache[$key]);
    }
  }
}
