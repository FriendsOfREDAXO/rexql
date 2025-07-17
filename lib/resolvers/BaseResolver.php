<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\RexQL\Resolver;

use Closure;
use FriendsOfRedaxo\RexQL\Context;
use FriendsOfRedaxo\RexQL\Services\Logger;
use FriendsOfRedaxo\RexQL\Utility;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Error\UserError;
use rex_sql;

use function array_merge_recursive;

abstract class BaseResolver
{

  protected array $args = [];
  protected Context $context;
  protected array $fields = [];
  protected ResolveInfo $info;
  protected string $joinClause = '';
  protected array $params = [];
  protected string $query = '';
  protected array $relationColumns = [];
  protected array $relations = [];
  protected array $root = [];
  protected string $selectClause = '';
  protected rex_sql $sql;
  protected string $table = '';
  protected string $typeName = '';
  protected array $fieldResolvers = [];
  protected array $whereParams = [];

  public function __construct()
  {
    $this->sql = rex_sql::factory();
  }

  public function resolve(): Closure
  {

    return function ($root, $args, $context, ResolveInfo $info): array {

      // Log the resolver call
      Logger::log('rexQL: Resolver: ' . $info->fieldName . ' called with args: ' . json_encode($args, JSON_PRETTY_PRINT));
      $this->root = $root;
      $this->typeName = $info->fieldName;
      $this->info = $info;
      $this->args = $args;
      $this->context = $context;
      return $this->getData();
    };
  }

  protected function getData()
  {
    // This method should be implemented in the child class to fetch data based on the arguments
    throw new \BadMethodCallException('fetchData method must be implemented in the child class.');
  }

  protected function query(): array
  {
    $fieldSelection = $this->info->getFieldSelection(5);
    $this->fields = $this->getFields($this->table, $fieldSelection);

    $query = $this->createQuery();
    if (empty($query)) {
      $this->error('Query could not be created. Please check your arguments and fields.');
    }

    try {
      $this->sql->setQuery($query, $this->whereParams);
    } catch (Error $e) {
      $this->error('Error creating query: ' . $e->getMessage());
    }

    $this->log('Base Resolver: query: ' . $query);

    if ($this->sql->getRows() === 0) {
      $this->error('No entries found matching the criteria.');
    }

    $result = $this->buildResult($this->sql->getArray());

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
        $whereClause .= "{$this->table}.{$column} IN (:{$column})";
      } else {
        $this->whereParams[$column] = $value;
        $whereClause .= "{$this->table}.{$column} = :{$column}";
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
    foreach ($result as $row) {
      $entry = [];
      $id = $row["{$this->table}_id"];
      if (!empty($this->relations)) {
        $relationResult = $this->buildRelationResult($row, $this->relations);
        $this->log('Base Resolver: relationResult: ' . json_encode($relationResult, \JSON_PRETTY_PRINT));
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
        $columnName = "{$this->table}_{$column}";
        $column = Utility::snakeCaseToCamelCase($column);
        if (isset($row[$columnName])) {
          $entry[$column] = $row[$columnName];
        }
      }
      if (isset($this->fieldResolvers[$this->table])) {
        foreach ($this->fieldResolvers[$this->table] as $field => $resolver) {
          if (in_array($field, $this->fields[$this->table]) && is_callable($resolver)) {
            $entry[$field] = $resolver($entry);
          }
        }
      }

      $tmpMainArray[$id] = $entry;
    }

    $this->log('Base Resolver: tmpRelationArray: ' . json_encode($tmpRelationArray, \JSON_PRETTY_PRINT));
    foreach ($tmpMainArray as $id => $entry) {
      if (isset($tmpRelationArray[$id])) {

        foreach ($tmpRelationArray[$id] as $relationEntries) {

          foreach ($relationEntries as $relationTypeName => $relationEntry) {
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
          }
        }
      }
      $resultArray[] = $entry;
    }

    return $resultArray;
  }

  protected function buildRelationResult(array $row, array $relations): mixed
  {
    $result = [];
    foreach ($relations as $relationTable => $args) {
      $hasMany = $args['type'] === 'hasMany';
      if (!isset($this->fields[$relationTable])) {
        continue;
      }
      $relationFields = $this->fields[$relationTable];
      $relationTypeName = Utility::snakeCaseToCamelCase($relationTable);
      $relationResult = $hasMany ? [] : null;
      $hasValue = false;
      foreach ($relationFields as $column) {
        $columnName = "{$relationTable}_{$column}";
        if (isset($row[$columnName])) {
          $relationResult[$column] = $row[$columnName];
          $hasValue = true;
        }
      }
      if (!$hasValue && !$hasMany) {
        return null; // If no values, set to null for hasOne relations
      }
      $relationResult['type'] = $args['type']; // Add type for easier identification
      $result[$relationTypeName] = $relationResult;
      if (isset($args['relations']) && is_array($args['relations'])) {
        $nestedResult = $this->buildRelationResult($row, $args['relations']);
        if (!empty($nestedResult)) {
          if (!isset($result[$relationTypeName])) {
            $result[$relationTypeName] = [];
          }
          $result[$relationTypeName] = array_merge_recursive(
            $result[$relationTypeName],
            $nestedResult
          );
        }
      }
    }

    return $result;
  }

  protected function handleRelations(string $table, array $relations): void
  {

    foreach ($relations as $relation => $options) {
      // $relationTable = Utility::camelCaseToSnakeCase($relation);
      $this->joinClause .= " LEFT JOIN {$relation} ON {$table}.{$options['localKey']} = {$relation}.{$options['foreignKey']}";
      if (!isset($this->fields[$relation])) {
        continue;
      }
      $relationFields = $this->fields[$relation];
      Logger::log('rexQL: Resolver: ' . $this->typeName . ' relation: ' . $relation . '; relationTable: ' . $relation . '; relationColumns: ' . \json_encode($relationFields, \JSON_PRETTY_PRINT));

      if (isset($options['relations']) && is_array($options['relations'])) {
        $this->handleRelations($relation, $options['relations']);
      }
    }
  }

  protected function getSelectArray(array $fields): array
  {
    $columns = [];
    foreach ($fields as $table => $columnsList) {
      foreach ($columnsList as $column) {
        if ($column === 'slug')
          continue; // Skip slug as it is handled separately 
        $columns[] = "{$table}.{$column} AS {$table}_{$column}";
      }
      if (!in_array('id', $columnsList)) {
        $columns[] = "{$table}.id AS {$table}_id"; // Ensure 'id' is always included
      }
    }
    return $columns;
  }

  protected function getFields(string $table, array $selection): array
  {
    $fields = [];
    $fields[$table] = [];
    foreach ($selection as $key => $value) {
      if (is_array($value)) {
        $nestedFields = $this->getFields($key, $value);
        foreach ($nestedFields as $nKey => $nValue) {
          $tableName = Utility::camelCaseToSnakeCase($nKey);
          $fields[$tableName] = $nValue;
        }
      } else {
        $fieldName = Utility::camelCaseToSnakeCase($key);
        $fields[$table][] = $fieldName;
      }
    }
    return $fields;
  }

  protected function log(string $message): void
  {
    Logger::log($message, 'info', __FILE__, __LINE__);
  }

  protected function error(string $message): void
  {
    throw new UserError($message);
  }
}
