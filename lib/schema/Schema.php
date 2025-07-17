<?php

namespace FriendsOfRedaxo\RexQL\SchemaGenerator;

use FriendsOfRedaxo\RexQL\Services\Logger;

use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;

use Psr\Cache\CacheItemPoolInterface;

use rex_addon;
use rex_api_exception;
use rex_dir;
use rex_file;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Generator
{

  protected bool $debugMode = false;

  protected string $schemaFilepath = '';
  protected string $cacheKey = '';
  protected string $cacheDirectory = '';

  protected ?int $globTTL = 3600;

  private CacheItemPoolInterface $cache;

  public function __construct(rex_addon $addon, $debugMode)
  {
    $this->schemaFilepath = $addon->getDataPath('schema.graphql');
    $this->debugMode = $debugMode;
    $filemtime = filemtime($this->schemaFilepath);
    $this->cacheKey = 'graphql_ast_' . md5($this->schemaFilepath . $filemtime);
    $this->cacheDirectory = $addon->getCachePath();
    $this->globTTL = $debugMode ? 3600 : null;
    $this->cache = new FilesystemAdapter('schema', $this->globTTL, $this->cacheDirectory);
  }

  public function generate(): Schema
  {
    $cacheItem = $this->cache->getItem($this->cacheKey);
    if ($cacheItem->isHit()) {
      Logger::log('rexQL: Generator: Schema: Cache hit! Loading from cache. ');
      $doc = $cacheItem->get();
      return (new BuildSchema($doc))->buildSchema();
    } else {
      rex_dir::delete($this->cacheDirectory, false);
    }

    $sdl = rex_file::get($this->schemaFilepath);
    if (!$sdl) {
      throw new rex_api_exception('rexQL: Generator: Schema: Schema file not found!');
    }

    $doc = Parser::parse($sdl);
    $cacheItem->set($doc);

    if ($this->globTTL !== null) {
      $cacheItem->expiresAfter($this->globTTL);
    }
    $saved = $this->cache->save($cacheItem);
    if (!$saved) {
      Logger::log('rexQL: Generator: Schema: Failed to save schema to cache.');
    } else {
      Logger::log('rexQL: Generator: Schema: Schema saved to cache successfully.');
    }
    return (new BuildSchema($doc))->buildSchema();
  }
}
