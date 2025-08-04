<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\RexQL\Resolver;

use BadMethodCallException;
use Closure;
use FriendsOfRedaxo\RexQL\Context;
use FriendsOfRedaxo\RexQL\Resolver\Interface\Resolver;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Utility;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Error\UserError;
use rex_sql;
use rex_sql_exception;

use function array_map;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_callable;

abstract class ResolverBase implements Resolver
{

  protected array $args = [];
  protected Context $context;
  protected array $ensureColumns = [];
  protected array $excludeFieldsFromSQL = [];
  protected array $fields = [];
  protected array $fieldsMap = [];
  protected array $fieldResolvers = [];
  protected ResolveInfo $info;
  protected string $joinClause = '';
  protected array $joinsUsed = [];
  protected array $mainIdColumns = [];
  protected array $params = [];
  protected string $query = '';
  protected array $relations = [];
  protected array $relationColumns = [];
  protected array $root = [];
  protected string $selectClause = '';
  protected rex_sql $sql;
  protected string $table = '';
  protected string $typeName = '';
  protected array $whereParams = [];

  public function __construct()
  {
    $this->sql = rex_sql::factory();
  }

  public function resolve(): Closure
  {

    return function (array $root, array $args, Context $context, ResolveInfo $info): array|null {

      // Log the resolver call
      $this->root = $root;
      $this->typeName = $info->fieldName;
      $this->info = $info;
      $this->args = $args;
      $this->context = $context;
      $this->sql->reset();
      $this->query = '';
      $this->selectClause = '';
      $this->joinClause = '';
      $returnType = Type::getNamedType($this->info->returnType);
      $returnTypeName = $returnType->name ?? '';
      $this->log('Base Resolver: resolve called for type: ' . json_encode(Type::getNamedType($this->info->returnType), JSON_PRETTY_PRINT));
      $this->checkPermissions($returnTypeName);
      return $this->getData();
    };
  }

  public function getData(): array|null
  {
    // This method should be implemented in the child class to fetch data based on the arguments
    throw new BadMethodCallException('fetchData method must be implemented in the child class.');
  }

  protected function query(): array
  {
    $fieldSelection = $this->info->getFieldSelection(5);
    $this->fields = $this->getFields($this->table, $fieldSelection);

    // Utility::clearRexSystemLog();
    $query = $this->createQuery();
    if (empty($query)) {
      $this->error('Query could not be created. Please check your arguments and fields.');
    }

    $this->log('Base Resolver: query: ' . $query);

    try {
      // @phpstan-ignore rexstan.rexSqlInjection
      $this->sql->setQuery($query, $this->whereParams);
    } catch (rex_sql_exception $e) {
      $this->error('Error creating query: ' . $e->getMessage());
    }

    if ($this->sql->getRows() === 0) {
      $this->log('No entries found matching the criteria.');
      return []; // Return empty array on error
    }

    $result = $this->buildResult($this->sql->getArray());
    $this->log('Base Resolver: result: ' . json_encode($result, \JSON_PRETTY_PRINT));

    return $result;
  }

  protected function createQuery()
  {
    $orderBy = $this->args['orderBy'] ?? "{$this->table}.priority ASC";
    $customWhere = $this->args['where'] ?? '';
    $limit = $this->args['limit'] ?? 0;
    $offset = $this->args['offset'] ?? 0;
    $whereColumns = Utility::deconstruct($this->args, ['orderBy', 'where', 'limit', 'offset']);

    $mainIdColumn = $this->getMainIdColumn($this->table);

    $this->setJoinClause($this->table, $this->relations);
    $this->selectClause = implode(', ', $this->getSelectArray($this->fields));

    $whereClause = '';
    foreach ($whereColumns as $key => $value) {
      $column = Utility::camelCaseToSnakeCase($key);
      $whereClause .= ' AND ';
      if (is_array($value)) {
        $this->whereParams[$column] = implode(',', array_map('intval', $value));
        $whereClause .= "{$this->table}.`{$column}` IN (:{$column})";
      } else {
        $this->whereParams[$column] = $value;
        $whereClause .= "{$this->table}.`{$column}` = :{$column}";
      }
    }
    if ($customWhere) {
      $whereClause .= " AND ({$customWhere})";
    }
    if ($whereClause)
      $whereClause = 'WHERE ' . ltrim($whereClause, ' AND ');

    if ($customWhere) {
      $whereClause .= $whereClause ? " AND ({$customWhere})" : "{$customWhere}";
    }

    $query = "SELECT {$this->selectClause} FROM (SELECT `{$mainIdColumn}` FROM {$this->table}";
    if ($limit > 0) {
      $query .= " LIMIT {$limit}";
    }
    if ($offset > 0) {
      $query .= " OFFSET {$offset}";
    }
    $query .= ") as `s0` JOIN {$this->table} ON {$this->table}.`{$mainIdColumn}` = `s0`.`{$mainIdColumn}` {$this->joinClause} {$whereClause}";
    if ($orderBy) {
      $query .= " ORDER BY {$orderBy}";
    }
    return $query;
  }

  protected function buildResult(array $result): array
  {
    $resultArray = [];
    $tmpMainArray = [];
    $usedIds = [];
    $mainIdColumn = $this->getMainIdColumn($this->table);

    foreach ($result as $row) {
      $entry = [];
      $id = $row["{$this->table}_{$mainIdColumn}"];
      if (!in_array($id, $usedIds)) {
        $usedIds[] = $id;

        foreach ($this->fields[$this->table] as $column) {
          $normalizedColumn = Utility::camelCaseToSnakeCase($column);
          $columnName = "{$this->table}_{$normalizedColumn}";
          if (isset($this->fieldsMap[$this->table]) && isset($this->fieldsMap[$this->table][$column])) {
            $mappedColumn = Utility::camelCaseToSnakeCase($this->fieldsMap[$this->table][$column]);
            $columnName = "{$this->table}_{$mappedColumn}";
          }
          if (isset($row[$columnName])) {
            $entry[$column] = $row[$columnName];
          }
        }
        if (isset($this->fieldResolvers[$this->table])) {
          foreach ($this->fieldResolvers[$this->table] as $field => $resolver) {
            if (in_array($field, $this->fields[$this->table]) && is_callable($resolver)) {
              $entry[$field] = $resolver($row);
            }
          }
        }
        $tmpMainArray[$id]['data'] = $entry;
      }

      if (empty($this->relations)) {
        continue; // No relations to process
      }

      if (!isset($tmpMainArray[$id])) {
        $tmpMainArray[$id] = [];
      }

      $relations = $this->buildRelationResult($this->table, $id, $this->relations, $row, $tmpMainArray);
      $tmpMainArray[$id] = array_merge($tmpMainArray[$id], $relations[$id] ?? []);
    }

    foreach ($tmpMainArray as $id => $entry) {

      $resultArray[$id] = !empty($entry['data']) ? $entry['data'] : [];
      if (isset($entry['relations'])) {
        $resultArray[$id] = array_merge($resultArray[$id], $this->mapRelationResult($entry['relations']));
      }
    }

    return array_values($resultArray);
  }

  protected function buildRelationResult(string $rootTable, int $rootId, array $relations, array $row, &$entries): mixed
  {

    foreach ($relations as $table => $options) {
      $hasMany = $options['type'] === 'hasMany';
      $alias = $options['alias'] ?? $table;
      $typeName = Utility::snakeCaseToCamelCase($alias);
      $fields = isset($this->fields[$typeName]) ? $this->fields[$typeName] : null;
      if (!$fields) {
        continue; // Skip if no fields are defined for this relation
      }
      $mainIdColumn = $this->getMainIdColumn($table);
      $identifier = "{$typeName}_{$options['localKey']}";
      $identifierMain = "{$typeName}_{$mainIdColumn}";
      $localId = isset($row[$identifier]) ? $row[$identifier] : $row[$identifierMain];
      if (!$hasMany && isset($entries[$rootId]['relations'][$typeName])) {
        // If it's a hasOne relation and already exists, skip to avoid overwriting
        continue;
      }
      $entry = [];
      foreach ($fields as $field) {
        $columnName = "{$alias}_" . Utility::camelCaseToSnakeCase($field);
        if (isset($row[$columnName])) {
          $entry[$field] = $row[$columnName];
        }
      }
      if (empty($entry)) {
        continue; // Skip if no fields are defined for this relation
      }
      $entries[$rootId]['relations'][$typeName][$localId]['data'] = $entry; // Add the relation entry to the main array 
      $entries[$rootId]['relations'][$typeName][$localId]['type'] = $options['type'];

      /*
      * nested relations
      */

      if (empty($options['relations'])) {
        continue; // No relations to process
      }

      $nestedRelations = $this->buildRelationResult(
        $alias,
        $localId,
        $options['relations'],
        $row,
        $entries[$rootId]['relations'][$typeName]
      );
      $entries[$rootId]['relations'][$typeName][$localId] = array_merge(
        $entries[$rootId]['relations'][$typeName][$localId],
        $nestedRelations[$localId] ?? []
      );

      /* 
      * nested relations end
      */
    }

    return $entries;
  }

  protected function mapRelationResult(array $relationArray): array
  {
    $mapped = [];
    if (empty($relationArray)) {
      return $mapped; // Return empty array if no relation data
    }
    foreach ($relationArray as $relationType => $relation) {
      foreach ($relation as $id => $relationData) {
        if ($relationData['type'] === 'hasOne') {
          $mapped[$relationType] = !empty($relationData['data']) ? $relationData['data'] : [];
        } else {
          $mapped[$relationType][$id] = !empty($relationData['data']) ? $relationData['data'] : [];
        }
        if (isset($relationData['relations'])) {
          $mapped[$relationType][$id] = array_merge($mapped[$relationType][$id], $this->mapRelationResult($relationData['relations']));
        }
      }
    }

    return $mapped;
  }

  protected function setJoinClause(string $table, array $relations): void
  {

    foreach ($relations as $relation => $options) {
      $normalizedRelation = $options['alias'] ?? Utility::snakeCaseToCamelCase($relation);
      if (!isset($this->fields[$normalizedRelation])) {
        continue;
      }
      $alias = $options['alias'] ?? $relation;
      if (!isset($this->fields[$alias])) {
        continue;
      }
      $this->checkPermissions($alias);
      if (isset($this->joinsUsed[$alias]) && in_array($alias, $this->joinsUsed[$alias])) {
        $alias .= '_' . count($this->joinsUsed[$alias]);
      }
      $this->joinsUsed[$alias][] = $alias;

      $relationAlias = $this->findRelationByTable($this->relations, $table);
      $tableAlias = $relationAlias ? $relationAlias['alias'] : $table;
      if (!isset($this->fields[$tableAlias])) {
        $tableAlias = Utility::camelCaseToSnakeCase($table);
      }
      $this->joinClause .= " LEFT JOIN {$relation} {$alias} ON {$tableAlias}.`{$options['localKey']}` = {$alias}.`{$options['foreignKey']}`";
      $this->relationColumns[] = "{$tableAlias}.`{$options['localKey']}` AS `{$tableAlias}_{$options['localKey']}`";
      $this->relationColumns[] = "{$alias}.`{$options['foreignKey']}` AS `{$alias}_{$options['foreignKey']}`";
      if (isset($options['relations']) && is_array($options['relations'])) {
        $this->setJoinClause($relation, $options['relations']);
      }
    }
  }

  protected function getSelectArray(array $fields): array
  {
    $excludeFieldsFromSQL = isset($this->excludeFieldsFromSQL[$this->table]) ? array_map('\FriendsOfRedaxo\RexQL\Utility::snakeCaseToCamelCase', $this->excludeFieldsFromSQL[$this->table]) : [];

    $columns = [];
    foreach ($fields as $table => $columnsList) {
      $normalizedTable = Utility::camelCaseToSnakeCase($table);
      $relation = $this->findRelationByAlias($this->relations, $table);
      $table = $relation ? Utility::camelCaseToSnakeCase($relation['key']) : $normalizedTable;
      $alias = $relation && isset($relation['alias']) ? $relation['alias'] : $normalizedTable;
      foreach ($columnsList as $column) {
        if (in_array($column, $excludeFieldsFromSQL))
          continue; // Skip as field is handled separately 
        if (isset($this->fieldsMap[$table]) && isset($this->fieldsMap[$table][$column])) {
          $column = $this->fieldsMap[$table][$column];
        }
        $column = Utility::camelCaseToSnakeCase($column);
        $columns[] = "{$alias}.`{$column}` AS `{$alias}_{$column}`";
      }
      // Ensure main 'id' is always included
      $mainIdColumn = $this->getMainIdColumn($table);
      if (!in_array($mainIdColumn, $columnsList)) {
        $columns[] = "{$alias}.`{$mainIdColumn}` AS `{$alias}_{$mainIdColumn}`";
      }
      // Ensure all ensureColumns are included
      if (!isset($this->ensureColumns[$table])) continue;
      foreach ($this->ensureColumns[$table] as $ensureColumn) {
        if (!in_array($ensureColumn, $columnsList)) {
          $columns[] = "{$alias}.`{$ensureColumn}` AS `{$alias}_{$ensureColumn}`";
        }
      }
    }
    // add relation data columns
    foreach ($this->relationColumns as $relationColumn) {
      if (!in_array($relationColumn, $columns)) {
        $columns[] = $relationColumn;
      }
    }
    return $columns;
  }

  protected function getMainIdColumn(string $table): string
  {
    $mainIdColumn = 'id';
    if (isset($this->mainIdColumns[$table]) && !empty($this->mainIdColumns[$table])) {
      $mainIdColumn = $this->mainIdColumns[$table];
    }
    return $mainIdColumn;
  }

  protected function findRelationByTable(array $array, string $searchTable): ?array
  {
    foreach ($array as $key => $value) {
      if (is_array($value) && $key === $searchTable) {
        return $value;
      }

      if (is_array($value)) {
        foreach ($value as $nestedKey => $nestedValue) {
          if (is_array($nestedValue)) {
            $result = $this->findRelationByTable([$nestedKey => $nestedValue], $searchTable);
            if ($result !== null) {
              return $result;
            }
          }
        }
      }
    }
    return null;
  }

  protected function findRelationByAlias(array $array, string $searchAlias): ?array
  {
    foreach ($array as $key => $value) {
      if (is_array($value) && isset($value['alias']) && $value['alias'] === $searchAlias) {
        return ['key' => $key, 'data' => $value];
      }

      if (is_array($value)) {
        foreach ($value as $nestedKey => $nestedValue) {
          if (is_array($nestedValue)) {
            $result = $this->findRelationByAlias([$nestedKey => $nestedValue], $searchAlias);
            if ($result !== null) {
              return $result;
            }
          }
        }
      }
    }
    return null;
  }

  public function checkPermissions(string $typeName): bool
  {
    $normalizedTypeName = $this->context->normalizeTypeName($typeName);

    $this->log('Checking permissions for type: ' . $normalizedTypeName);
    $hasPermission = $this->context->hasPermission($normalizedTypeName);
    if (!$hasPermission) {
      $this->error("You do not have permission to access {$normalizedTypeName}.");
    }
    return true;
  }

  public function getFields(string $table, array $selection): array
  {
    $fields = [];
    $fields[$table] = [];
    foreach ($selection as $fieldName => $value) {
      if (is_array($value)) {
        $nestedFields = $this->getFields($fieldName, $value);
        foreach ($nestedFields as $tableName => $nValue) {
          // $tableName = Utility::camelCaseToSnakeCase($nKey);
          $fields[$tableName] = $nValue;
        }
      } else {
        // $fieldName = Utility::camelCaseToSnakeCase($key);
        $fields[$table][] = $fieldName;
      }
    }
    return $fields;
  }

  public function log(string $message): void
  {
    Logger::log($message, 'debug', __FILE__, __LINE__);
  }

  public function error(string $message): void
  {
    throw new UserError($message);
  }
}
