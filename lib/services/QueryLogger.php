<?php

namespace FriendsOfRedaxo\RexQL\Services;

/**
 * Query-Logger für rexQL
 */
class QueryLogger
{
  /**
   * Query-Ausführung protokollieren
   */
  public static function log(
    ?int $apiKeyId,
    string $query,
    ?array $variables,
    float $executionTime,
    float $memoryUsage,
    bool $success,
    ?string $errorMessage = null,
    bool $fromCache = false
  ): void {
    $sql = \rex_sql::factory();
    $sql->setTable(\rex::getTable('rexql_query_log'));
    $sql->setValue('api_key_id', $apiKeyId);
    $sql->setValue('query', $query);
    $sql->setValue('variables', $variables ? json_encode($variables) : null);
    $sql->setValue('execution_time', $executionTime);
    $sql->setValue('memory_usage', $memoryUsage);
    $sql->setValue('success', $success ? 1 : 0);
    $sql->setValue('error_message', $errorMessage);
    $sql->setValue('ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
    $sql->setValue('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $sql->setValue('from_cache', $fromCache ? 1 : 0);
    $sql->setValue('createdate', date('Y-m-d H:i:s'));

    try {
      $sql->insert();
    } catch (\rex_sql_exception $e) {
      // Don't throw log errors to avoid affecting the original query
      error_log('rexQL QueryLogger Error: ' . $e->getMessage());
    }
  }

  /**
   * Get statistics
   */
  public static function getStats(): array
  {
    $sql = \rex_sql::factory();

    // Gesamtstatistiken
    $sql->setQuery('
            SELECT 
                COUNT(*) as total_queries,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_queries,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_queries,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                AVG(memory_usage) as avg_memory_usage,
                MAX(memory_usage) as max_memory_usage,
                SUM(CASE WHEN from_cache = 1 THEN 1 ELSE 0 END) as cached_queries
            FROM ' . \rex::getTable('rexql_query_log') . '
            WHERE createdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ');

    $stats = $sql->getArray()[0];

    // Top API Keys (nach Anzahl Queries)
    $sql->setQuery('
            SELECT 
                ak.name,
                COUNT(*) as query_count
            FROM ' . \rex::getTable('rexql_query_log') . ' ql
            LEFT JOIN ' . \rex::getTable('rexql_api_keys') . ' ak ON ql.api_key_id = ak.id
            GROUP BY ql.api_key_id
            ORDER BY query_count DESC
            LIMIT 5
        ');

    $stats['top_api_keys'] = $sql->getArray();

    // Most common errors
    $sql->setQuery('
            SELECT 
                error_message,
                COUNT(*) as error_count
            FROM ' . \rex::getTable('rexql_query_log') . '
            WHERE success = 0 AND createdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY error_message
            ORDER BY error_count DESC
            LIMIT 5
        ');

    $stats['top_errors'] = $sql->getArray();

    // Most expensive queries
    $sql->setQuery('
            SELECT 
                query,
                execution_time,
                memory_usage,
                createdate
            FROM ' . \rex::getTable('rexql_query_log') . '
            WHERE createdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY execution_time DESC
            LIMIT 5
        ');
    $stats['expensive_queries'] = $sql->getArray();

    // Most recent queries
    $sql->setQuery('
            SELECT 
                ak.name,
                ql.query,
                ql.execution_time,
                ql.memory_usage,
                ql.createdate,
                ql.success,
                ql.error_message
            FROM ' . \rex::getTable('rexql_query_log') . ' ql
            LEFT JOIN ' . \rex::getTable('rexql_api_keys') . ' ak ON ql.api_key_id = ak.id
            WHERE ql.createdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY ql.createdate DESC
            LIMIT 5
        ');
    $stats['recent_queries'] = $sql->getArray();

    return $stats;
  }

  /**
   * Alte Log-Einträge bereinigen
   */
  public static function cleanup(int $daysToKeep = 30): int
  {
    $sql = \rex_sql::factory();
    $sql->setQuery(
      'DELETE FROM ' . \rex::getTable('rexql_query_log') . ' 
             WHERE createdate < DATE_SUB(NOW(), INTERVAL ? DAY)',
      [$daysToKeep]
    );

    return $sql->getRows();
  }
}
