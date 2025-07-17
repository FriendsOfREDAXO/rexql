<?php

namespace FriendsOfRedaxo\RexQL;

use rex_sql;
use rex_sql_exception;

/**
 * API key management class
 */
class ApiKey
{
  private int $id;
  private string $name;
  private string $apiKey;
  private ?string $publicKey;    // New public key for frontend
  private ?string $privateKey;   // Private key for backend proxy
  private array $permissions;
  private int $rateLimit;
  private ?\DateTime $lastUsed;
  private int $usageCount;
  private bool $active;
  private string $createdBy;
  private \DateTime $createdate;
  private \DateTime $updatedate;

  // Domain-Restrictions
  private array $allowedDomains;
  private array $allowedIps;
  private bool $httpsOnly;
  private string $keyType; // 'standard', 'public_private', 'domain_restricted'

  /**
   * Find API key by key string
   */
  public static function findByKey(string $apiKey): ?self
  {
    $sql = \rex_sql::factory();
    $sql->setQuery(
      'SELECT * FROM ' . \rex::getTable('rexql_api_keys') . ' WHERE api_key = ? AND active = 1',
      [$apiKey]
    );

    if ($sql->getRows() === 0) {
      return null;
    }

    return self::fromDbResult($sql);
  }

  /**
   * Get all active API keys
   * 
   * @return self[]
   */
  public static function getAll(): array
  {
    $sql = \rex_sql::factory();
    $sql->setQuery('SELECT * FROM ' . \rex::getTable('rexql_api_keys') . ' ORDER BY createdate DESC');

    $keys = [];
    while ($sql->hasNext()) {
      $keys[] = self::fromDbResult($sql);
      $sql->next();
    }

    return $keys;
  }

  /**
   * Create a new API key
   */
  public static function create(string $name, array $permissions = [], int $rateLimit = 100): self
  {
    $apiKey = self::generateApiKey();

    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_api_keys'));
    $sql->setValue('name', $name);
    $sql->setValue('api_key', $apiKey);
    $sql->setValue('permissions', json_encode($permissions));
    $sql->setValue('rate_limit', $rateLimit);
    $sql->setValue('active', 1);
    $sql->setValue('created_by', \rex::getUser()->getLogin());
    $sql->setValue('createdate', date('Y-m-d H:i:s'));
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));

    try {
      $sql->insert();
      return self::findByKey($apiKey);
    } catch (rex_sql_exception $e) {
      throw new \Exception('Fehler beim Erstellen des API-Schlüssels: ' . $e->getMessage());
    }
  }

  /**
   * Generate a unique API key
   */
  private static function generateApiKey(): string
  {
    return 'rexql_' . bin2hex(random_bytes(28));
  }

  /**
   * Verwendung protokollieren
   */
  public function logUsage(): void
  {
    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_api_keys'));
    $sql->setWhere(['id' => $this->id]);
    $sql->setValue('last_used', date('Y-m-d H:i:s'));
    $sql->setValue('usage_count', $this->usageCount + 1);
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));
    $sql->update();

    $this->lastUsed = new \DateTime();
    $this->usageCount++;
  }

  /**
   * Check if a specific permission is granted
   */
  public function hasPermission(string $permission): bool
  {
    return in_array($permission, $this->permissions) || in_array('read:all', $this->permissions);
  }

  /**
   * Check if the API key has reached its rate-limit
   */
  public function isRateLimitedExceeded(): bool
  {
    $sql = \rex_sql::factory();
    $sql->setQuery(
      'SELECT COUNT(*) as count FROM ' . \rex::getTable('rexql_query_log') . ' 
             WHERE api_key_id = ? AND createdate > DATE_SUB(NOW(), INTERVAL 1 MINUTE)',
      [$this->id]
    );

    return $sql->getValue('count') >= $this->rateLimit;
  }

  /**
   * Create an instance from a database result
   */
  private static function fromDbResult(\rex_sql $sql): self
  {
    $instance = new self();
    $instance->id = (int) $sql->getValue('id');
    $instance->name = $sql->getValue('name');
    $instance->apiKey = $sql->getValue('api_key');
    $instance->permissions = json_decode($sql->getValue('permissions') ?: '[]', true) ?: [];
    $instance->rateLimit = (int) $sql->getValue('rate_limit');
    $instance->lastUsed = $sql->getValue('last_used') ? new \DateTime($sql->getValue('last_used')) : null;
    $instance->usageCount = (int) $sql->getValue('usage_count');
    $instance->active = (bool) $sql->getValue('active');
    $instance->createdBy = $sql->getValue('created_by');
    $instance->createdate = new \DateTime($sql->getValue('createdate'));
    $instance->updatedate = new \DateTime($sql->getValue('updatedate'));

    // Handle new columns that might not exist in older installations
    try {
      $instance->publicKey = $sql->getValue('public_key');
    } catch (\rex_sql_exception $e) {
      $instance->publicKey = null;
    }

    try {
      $instance->privateKey = $sql->getValue('private_key');
    } catch (\rex_sql_exception $e) {
      $instance->privateKey = null;
    }

    try {
      $instance->allowedDomains = json_decode($sql->getValue('allowed_domains') ?: '[]', true) ?: [];
    } catch (\rex_sql_exception $e) {
      $instance->allowedDomains = [];
    }

    try {
      $instance->allowedIps = json_decode($sql->getValue('allowed_ips') ?: '[]', true) ?: [];
    } catch (\rex_sql_exception $e) {
      $instance->allowedIps = [];
    }

    try {
      $instance->httpsOnly = (bool) $sql->getValue('https_only');
    } catch (\rex_sql_exception $e) {
      $instance->httpsOnly = false;
    }

    try {
      $instance->keyType = $sql->getValue('key_type') ?: 'standard';
    } catch (\rex_sql_exception $e) {
      $instance->keyType = 'standard';
    }

    return $instance;
  }

  /**
   * Extended API key with Public/Private keys
   */
  public static function createPublicPrivateKey(
    string $name,
    array $permissions,
    int $rateLimit = 60,
    array $allowedDomains = [],
    array $allowedIps = [],
    bool $httpsOnly = false
  ): self {
    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_api_keys'));

    $publicKey = self::generatePublicKey();
    $privateKey = self::generatePrivateKey();

    $sql->setValue('name', $name);
    $sql->setValue('api_key', $publicKey); // Public key as API key
    $sql->setValue('public_key', $publicKey);
    $sql->setValue('private_key', $privateKey);
    $sql->setValue('permissions', json_encode($permissions));
    $sql->setValue('rate_limit', $rateLimit);
    $sql->setValue('allowed_domains', json_encode($allowedDomains));
    $sql->setValue('allowed_ips', json_encode($allowedIps));
    $sql->setValue('https_only', $httpsOnly ? 1 : 0);
    $sql->setValue('key_type', 'public_private');
    $sql->setValue('active', 1);
    $sql->setValue('created_by', \rex::getUser()->getLogin());
    $sql->setValue('createdate', date('Y-m-d H:i:s'));
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));

    try {
      $sql->insert();
      return self::findByKey($publicKey);
    } catch (rex_sql_exception $e) {
      throw new \Exception('Fehler beim Erstellen des API-Schlüssels: ' . $e->getMessage());
    }
  }

  /**
   * Generates a public key for frontend use
   */
  private static function generatePublicKey(): string
  {
    return 'rexql_pub_' . bin2hex(random_bytes(24));
  }

  /**
   * Generates a private key for backend use
   */
  private static function generatePrivateKey(): string
  {
    return 'rexql_priv_' . bin2hex(random_bytes(32));
  }

  /**
   * Generates a domain-restricted API key
   */
  public static function createDomainRestrictedKey(
    string $name,
    array $permissions,
    int $rateLimit = 60,
    array $allowedDomains = [],
    array $allowedIps = [],
    bool $httpsOnly = false
  ): self {
    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_api_keys'));

    $apiKey = self::generateApiKey();

    $sql->setValue('name', $name);
    $sql->setValue('api_key', $apiKey);
    $sql->setValue('permissions', json_encode($permissions));
    $sql->setValue('rate_limit', $rateLimit);
    $sql->setValue('allowed_domains', json_encode($allowedDomains));
    $sql->setValue('allowed_ips', json_encode($allowedIps));
    $sql->setValue('https_only', $httpsOnly ? 1 : 0);
    $sql->setValue('key_type', 'domain_restricted');
    $sql->setValue('active', 1);
    $sql->setValue('created_by', \rex::getUser()->getLogin());
    $sql->setValue('createdate', date('Y-m-d H:i:s'));
    $sql->setValue('updatedate', date('Y-m-d H:i:s'));

    try {
      $sql->insert();
      return self::findByKey($apiKey);
    } catch (rex_sql_exception $e) {
      throw new \Exception('Fehler beim Erstellen des API-Schlüssels: ' . $e->getMessage());
    }
  }

  // Getter methods
  public function getId(): int
  {
    return $this->id;
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getApiKey(): string
  {
    return $this->apiKey;
  }

  public function getPublicKey(): ?string
  {
    return $this->publicKey;
  }

  public function getPrivateKey(): ?string
  {
    return $this->privateKey;
  }

  public function getPermissions(): array
  {
    return $this->permissions;
  }

  public function getRateLimit(): int
  {
    return $this->rateLimit;
  }

  public function getLastUsed(): ?\DateTime
  {
    return $this->lastUsed;
  }

  public function getUsageCount(): int
  {
    return $this->usageCount;
  }

  public function isActive(): bool
  {
    return $this->active;
  }

  public function getCreatedBy(): string
  {
    return $this->createdBy;
  }

  public function getCreateDate(): \DateTime
  {
    return $this->createdate;
  }

  public function getUpdateDate(): \DateTime
  {
    return $this->updatedate;
  }

  public function getAllowedDomains(): array
  {
    return $this->allowedDomains;
  }

  public function getAllowedIps(): array
  {
    return $this->allowedIps;
  }

  public function isHttpsOnly(): bool
  {
    return $this->httpsOnly;
  }

  public function getKeyType(): string
  {
    return $this->keyType;
  }
}
