<?php

namespace FriendsOfRedaxo\RexQL;

use FriendsOfRedaxo\RexQL\ApiKey;

use rex_sql;

class Context
{
  public rex_sql $sql;
  public ?ApiKey $apiKey;

  protected array $data = [];

  public function __construct()
  {

    $this->sql = rex_sql::factory();
  }

  public function setApiKey(ApiKey $apiKey): void
  {
    $this->apiKey = $apiKey;
  }

  public function getApiKey(): ?ApiKey
  {
    return $this->apiKey;
  }

  public function set(string $key, $value): void
  {
    $this->data[$key] = $value;
  }

  public function get(string $key, $default = null)
  {
    // Return the value if it exists, otherwise return the default value
    return $this->data[$key] ?? $default;
  }
}
