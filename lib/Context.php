<?php

namespace FriendsOfRedaxo\RexQL;

use FriendsOfRedaxo\RexQL\ApiKey;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Utility;

class Context
{
  public ?ApiKey $apiKey;

  protected array $data = [];

  public function setApiKey(?ApiKey $apiKey): void
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

  public function has(string $key): bool
  {
    // Check if the key exists in the data array
    return isset($this->data[$key]);
  }

  public function hasPermission(string $typeName, string $type = 'read'): bool
  {
    $configCheckPassed = $this->get('configCheckPassed', false);

    if (!$configCheckPassed) {
      return false; // Configuration check failed, skip permission check
    }
    $permissionName = $type . ':' . $typeName;
    $apiKey = $this->getApiKey();
    if ($apiKey) {
      $permissions = $apiKey->getPermissions();
      Logger::log('Checking permissions for type: ' . $permissionName . ' permissions: ' . print_r($permissions, true), 'debug', __FILE__, __LINE__);
      if (empty($permissions)) {
        return false; // No permissions set for the API key
      }
      if ($permissionName === $type . ':all') return true;
      if (!in_array($permissionName, $permissions)) {
        return false; // Type not allowed by API key permissions
      }
    }
    return true; // Default to true if no API key or permissions are set
  }

  public function normalizeTypeName(string $typeName): string
  {
    // Normalize type names to match GraphQL conventions
    if (str_ends_with($typeName, 'ies')) {
      return substr($typeName, 0, -3) . 'y';
    } elseif (str_ends_with($typeName, 's')) {
      return substr($typeName, 0, -1); // Remove 's' suffix
    }
    return $typeName; // Return as is if no suffix matches
  }
}
