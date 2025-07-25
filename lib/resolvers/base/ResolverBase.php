<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\RexQL\Resolver;

use Closure;
use FriendsOfRedaxo\RexQL\Context;
use FriendsOfRedaxo\RexQL\Resolver\Interface\Resolver;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Utility;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Error\UserError;
use rex_sql;
use BadMethodCallException;

use function is_array;
use function json_encode;
use function is_callable;
use function in_array;
use function implode;
use function array_map;
use function array_merge_recursive;

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
  protected array $mainIdColumns = [];
  protected array $params = [];
  protected string $query = '';
  protected array $relationColumns = [];
  protected array $relations = [];
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

    return function (array $root, array $args, Context $context, ResolveInfo $info): array {

      // Log the resolver call
      $this->root = $root;
      $this->typeName = $info->fieldName;
      $this->info = $info;
      $this->args = $args;
      $this->context = $context;
      return $this->getData();
    };
  }

  public function getData(): array
  {
    // This method should be implemented in the child class to fetch data based on the arguments
    throw new BadMethodCallException('fetchData method must be implemented in the child class.');
  }

  protected function query(): array
  {
    $fieldSelection = $this->info->getFieldSelection(5);
    $this->fields = $this->getFields($this->table, $fieldSelection);

    $query = $this->createQuery();
    if (empty($query)) {
      $this->error('Query could not be created. Please check your arguments and fields.');
    }
    $this->log('Base Resolver: query: ' . $query);

    try {
      $this->sql->setQuery($query, $this->whereParams);
    } catch (Error $e) {
      $this->error('Error creating query: ' . $e->getMessage());
    }

    if ($this->sql->getRows() === 0) {
      $this->error('No entries found matching the criteria.');
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

    $this->selectClause = implode(', ', $this->getSelectArray($this->fields));
    $this->handleRelations($this->table, $this->relations);

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

    $query = "SELECT {$this->selectClause} FROM {$this->table}{$this->joinClause} {$whereClause}";
    if ($orderBy) {
      $query .= " ORDER BY {$orderBy}";
    }
    if ($limit > 0) {
      $query .= " LIMIT {$limit}";
    }
    if ($offset > 0) {
      $query .= " OFFSET {$offset}";
    }

    return $query;
  }

  protected function buildResult(array $result): array
  {
    $tmpMainArray = [];
    $tmpRelationArray = [];
    $usedIds = [];
    $mainIdColumn = $this->getMainId($this->table);


    foreach ($result as $row) {
      $entry = [];
      $id = $row["{$this->table}_{$mainIdColumn}"];
      if (!empty($this->relations)) {
        $relationResult = $this->buildRelationResult($row, $this->relations);
        if ($relationResult === null) {
          continue; // Skip if no relation result
        }
        $tmpRelationArray[$id][] = $relationResult;
      }

      if (in_array($id, $usedIds)) {
        continue;
      }
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
      $tmpMainArray[$id] = $entry;
    }
    $this->log('Mapping tmpRelationArray for ' . json_encode($tmpRelationArray, JSON_PRETTY_PRINT));

    foreach ($tmpMainArray as $id => $entry) {
      if (isset($tmpRelationArray[$id])) {

        foreach ($tmpRelationArray[$id] as $relationEntries) {

          // foreach ($relationEntries as $relationTypeName => $relationEntry) {
          //   $hasMany = $relationEntry['type'] === 'hasMany';
          //   unset($relationEntry['type']); // Remove type as it's not needed in the final result
          //   if (!isset($entry[$relationTypeName])) {
          //     $entry[$relationTypeName] = [];
          //   }
          //   if (is_array($relationEntry) && !empty($relationEntry)) {
          //     if ($hasMany) {
          //       $entry[$relationTypeName][] = $relationEntry;
          //     } else {
          //       $entry[$relationTypeName] = $relationEntry; // For hasOne relations, replace the entry
          //     }
          //   } elseif (is_array($relationEntry) && empty($relationEntry)) {
          //     $entry[$relationTypeName] = []; // If no values, set to null
          //   }
          // }
          $entry = $this->mapFinalRelationResult($relationEntries, $entry);
        }
      }
      $resultArray[] = $entry;
    }

    return $resultArray;
  }

  protected function mapFinalRelationResult(array $entries, array &$entry): array
  {

    foreach ($entries as $relationTypeName => $relationEntry) {
      $hasMany = $relationEntry['type'] === 'hasMany';
      unset($relationEntry['type']); // Remove type as it's not needed in the final result
      if (!isset($entry[$relationTypeName])) {
        $entry[$relationTypeName] = [];
      }
      if (is_array($relationEntry) && !empty($relationEntry)) {
        if ($hasMany) {
          $entry[$relationTypeName][] = $relationEntry;
        } else {
          $entry[$relationTypeName] = $relationEntry; // For hasOne relations, replace the entry
        }
      } elseif (is_array($relationEntry) && empty($relationEntry)) {
        $entry[$relationTypeName] = []; // If no values, set to null
      }
      // $this->log('Mapping nested relations for ' . print_r($relationEntry, true));
      // if (isset($relationEntry['relations']) && is_array($relationEntry['relations'])) {
      //   $nestedResult = $this->mapFinalRelationResult($relationEntry['relations'], $entry[$relationTypeName]);
      //   if (!empty($nestedResult)) {
      //     $entry[$relationTypeName] = array_merge_recursive(
      //       $entry[$relationTypeName],
      //       $nestedResult
      //     );
      //   }
      // }
    }
    return $entry;
  }

  protected function buildRelationResult(array $row, array $relations): mixed
  {
    $result = [];
    foreach ($relations as $relationTable => $options) {
      $hasMany = $options['type'] === 'hasMany';
      $relationTypeName = $options['alias'] ?? Utility::snakeCaseToCamelCase($relationTable);
      $relationTableAlias = $options['alias'] ?? $relationTable;

      if (!isset($this->fields[$relationTypeName])) {
        continue;
      }
      $relationFields = $this->fields[$relationTypeName];
      $relationResult = $hasMany ? [] : null;
      $hasValue = false;
      foreach ($relationFields as $column) {
        $columnName = "{$relationTableAlias}_{$column}";
        if (isset($row[$columnName])) {
          $relationResult[$column] = $row[$columnName];
          $hasValue = true;
        }
      }
      if (!$hasValue && !$hasMany) {
        return null; // If no values, set to null for hasOne relations
      }
      $relationResult['type'] = $options['type']; // Add type for easier identification
      $result[$relationTypeName] = $relationResult;
      if (isset($options['relations']) && is_array($options['relations'])) {
        $nestedResult = $this->buildRelationResult($row, $options['relations']);
        $this->log('Building nested relations for ' . $relationTypeName . '; result:' . print_r($nestedResult, true));
        if (!empty($nestedResult)) {
          $result[$relationTypeName] = array_merge_recursive(
            $result[$relationTypeName],
            $nestedResult
          );
          // foreach ($nestedResult as $nestedRelationTypeName => $nestedRelationEntry) {
          //   $nestedHasMany = $nestedRelationEntry['type'] === 'hasMany';
          //   if (!isset($result[$relationTypeName])) {
          //     $result[$relationTypeName] = $nestedHasMany ? [] : null;
          //   }
          //   if (!isset($result[$relationTypeName][$nestedRelationTypeName])) {
          //     $result[$relationTypeName][$nestedRelationTypeName] = [];
          //   }
          //   if ($nestedHasMany) {
          //     $result[$relationTypeName][$nestedRelationTypeName][] = $nestedRelationEntry;
          //   } else {
          //     $result[$relationTypeName][$nestedRelationTypeName] = $nestedRelationEntry; // For hasOne relations, replace the entry
          //   }
          // }
        }
      }
    }

    return $result;
  }

  protected function handleRelations(string $table, array $relations): void
  {

    foreach ($relations as $relation => $options) {
      $normalizedRelation = $options['alias'] ?? Utility::snakeCaseToCamelCase($relation);
      if (!isset($this->fields[$normalizedRelation])) {
        continue;
      }
      $alias = $options['alias'] ?? $relation;
      $relationAlias = $this->findRelationByTable($this->relations, $table);
      $tableAlias = $relationAlias ? $relationAlias['alias'] : $table;
      $this->joinClause .= " LEFT JOIN {$relation} {$alias} ON {$tableAlias}.`{$options['localKey']}` = {$alias}.`{$options['foreignKey']}`";
      if (isset($options['relations']) && is_array($options['relations'])) {
        $this->handleRelations($relation, $options['relations']);
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
      $mainIdColumn = $this->getMainId($table);
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
    return $columns;
  }

  protected function getMainId(string $table): string
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
    Logger::log($message, 'info', __FILE__, __LINE__);
  }

  public function error(string $message): void
  {
    throw new UserError($message);
  }
}
