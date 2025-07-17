<?php

namespace FriendsOfRedaxo\RexQL;

use FriendsOfRedaxo\RexQL\Resolvers\Resolver;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use rex;
use rex_addon;
use rex_sql;
use rex_yform_manager_table;
use rex_logger;
use rex_config;
use rex_clang;


/**
 * GraphQL Schema Builder for REDAXO
 */
class SchemaBuilder
{
  private ?rex_addon $addon = null;
  private bool $debugMode = false;
  private array $types = [];
  private array $queries = [];
  private array $mutations = [];
  private ?rex_logger $logger = null;

  private Resolver $resolver;

  function __construct()
  {
    $this->resolver = new Resolver();
    $this->addon = rex_addon::get('rexql');
    $this->debugMode = $this->addon->getConfig('debug_mode', false);
    $this->logger = rex_logger::factory();
  }

  public function log(string $message, string $type = 'info'): void
  {
    if ($this->debugMode) {
      $this->logger->log($type, $message, [], __FILE__, __LINE__);
    }
  }

  /**
   * Table configurations for arguments and special handling
   */
  private function getTableConfigurations(): array
  {

    return [
      'rex_article' => [
        'description' => 'REDAXO Articles',
        'args' => [
          'id' => ['type' => 'int'],
          'status' => ['type' => 'int', 'defaultValue' => 1],
          'clang_id' => ['type' => 'int', 'defaultValue' => 1],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'priority ASC, catpriority ASC'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'limit' => ['type' => 'int'],
        ],
        'fields' => [
          'name' => ['description' => 'Article name'],
          'status' => ['description' => 'Article status'],
          'clang_id' => ['description' => 'Language ID'],
          'parent_id' => ['description' => 'Parent article ID']
        ],
        'relations' => [
          'rex_article_slice' => [
            'type' => '1:n',
            'foreign_key' => 'article_id',
            'local_key' => 'id',
            'order_by' => 'priority ASC'
          ]
        ]
      ],
      'rex_article_slice' => [
        'description' => 'REDAXO Article Slices',
        'args' => [
          'id' => ['type' => 'int'],
          'status' => ['type' => 'int', 'defaultValue' => 1],
          'article_id' => ['type' => 'int'],
          'clang_id' => ['type' => 'int', 'defaultValue' => 1],
          'module_id' => ['type' => 'int'],
          'ctype_id' => ['type' => 'int'],
          'limit' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'priority ASC'],
        ],
        'fields' => [
          'article_id' => ['description' => 'Article ID'],
          'clang_id' => ['description' => 'Language ID'],
          'module_id' => ['description' => 'Module ID'],
          'ctype_id' => ['description' => 'Content type ID'],
          'priority' => ['description' => 'Priority/order'],
          'value1' => ['description' => 'Module value 1'],
          'value2' => ['description' => 'Module value 2']
        ],
        'relations' => [
          'rex_module' => [
            'type' => 'n:1',
            'foreign_key' => 'id',
            'local_key' => 'module_id',
          ]
        ]
        // 'joins' => [
        //   'rex_module' => [
        //     'type' => 'LEFT JOIN',
        //     'on' => 'rex_article_slice.module_id = rex_module.id',
        //     'alias' => 'rex_module',
        //     'fields' => [
        //       'module_id' => 'rex_module.id',
        //       'module_name' => 'rex_module.name',
        //       'module_key' => 'rex_module.key',
        //       'module_input' => 'rex_module.input',
        //       'module_output' => 'rex_module.output'
        //     ]
        //   ]
        // ]
      ],
      'rex_clang' => [
        'description' => 'REDAXO Languages',
        'args' => [
          'id' => ['type' => 'int'],
          'status' => ['type' => 'int', 'defaultValue' => 1],
          'code' => ['type' => 'string'],
          'limit' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'priority ASC'],
        ],
        'fields' => [
          'id' => ['description' => 'Language ID'],
          'name' => ['description' => 'Language Name'],
          'code' => ['description' => 'Language Code']
        ]
      ],
      'rex_media' => [
        'description' => 'REDAXO Media',
        'fields' => [
          'id' => ['description' => 'Media ID'],
          'filename' => ['description' => 'Filename'],
          'title' => ['description' => 'Title']
        ]
      ],
      'rex_media_category' => [
        'description' => 'REDAXO Media Categories',
        'fields' => [
          'id' => ['description' => 'Media Category ID'],
          'name' => ['description' => 'Name'],
          'parent_id' => ['description' => 'Parent Category ID']
        ]
      ],
      'rex_module' => [
        'description' => 'REDAXO Modules',
        'args' => [
          'id' => ['type' => 'int'],
          'key' => ['type' => 'string'],
          'name' => ['type' => 'string'],
          'limit' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id ASC'],
        ],
        'fields' => [
          'id' => ['description' => 'Module ID'],
          'key' => ['description' => 'Key'],
          'name' => ['description' => 'Name'],
          'input' => ['description' => 'Input Code'],
          'output' => ['description' => 'Output Code'],
        ]
      ],
      'rex_template' => [
        'description' => 'REDAXO Templates',
        'fields' => [
          'id' => ['description' => 'Template ID'],
          'key' => ['description' => 'Key'],
          'name' => ['description' => 'Name'],
        ]
      ]
    ];
  }

  private function getDefaultConfiguration(): array
  {
    return  [
      'description' => 'Database table',
      'args' => [
        'id' => ['type' => 'int'],
        'limit' => ['type' => 'int'],
        'offset' => ['type' => 'int', 'defaultValue' => 0],
        'where' => ['type' => 'string'],
        'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
      ],
      'fields' => []
    ];
  }

  /**
   * Create complete GraphQL schema
   */
  public function buildSchema(): Schema
  {
    // Debug logging for schema building
    $this->log('RexQL: Building fresh GraphQL schema');

    $this->buildCoreTypes();
    $this->buildYFormTypes();
    $this->buildCustomTypes();

    return new Schema([
      'query' => new ObjectType([
        'name' => 'Query',
        'fields' => $this->queries
      ]),
      // 'mutation' => new ObjectType([
      //   'name' => 'Mutation',
      //   'fields' => $this->mutations
      // ]),
      'types' => array_values($this->types)
    ]);
  }

  /**
   * Create core table types
   */

  private function buildCoreTypes(): void
  {
    $allowedTables = rex_addon::get('rexql')->getConfig('allowed_tables', []);
    $coreTables = $this->getTableConfigurations();

    // Debug: Output allowed tables
    $this->log("RexQL: Allowed tables: " . implode(', ', $allowedTables), 'debug');
    $this->log("RexQL: Available configurations: " . implode(', ', array_keys($coreTables)), 'debug');

    // First phase: Create all types (without relations)
    foreach ($coreTables as $table => $config) {
      if (!in_array($table, $allowedTables)) {
        $this->log("RexQL: Skipping table '{$table}' - not in allowed tables", 'debug');
        continue;
      }

      $typeName = $this->getTypeName($table);
      $this->log("RexQL: Creating type '{$typeName}' for table '{$table}' (Phase 1)");

      // Create first without relations
      $configWithoutRelations = $config;
      unset($configWithoutRelations['relations']);

      try {
        $this->types[$typeName] = $this->createTypeFromTable($table, $configWithoutRelations);
      } catch (\Exception $e) {
        $this->logger->alert("RexQL: Error creating type '{$typeName}': " . $e->getMessage());
        throw $e;
      }
    }

    // Second phase: Update types with relations
    foreach ($coreTables as $table => $config) {
      if (!in_array($table, $allowedTables) || empty($config['relations'])) {
        continue;
      }

      $typeName = $this->getTypeName($table);
      $this->log("RexQL: Updating type '{$typeName}' with relations (Phase 2)");

      // Check if all referenced types exist
      foreach ($config['relations'] as $relationTable => $relationConfig) {
        $relationTypeName = $this->getTypeName($relationTable);
        if (!isset($this->types[$relationTypeName])) {
          $this->log("RexQL: Referenced type '{$relationTypeName}' for table '{$relationTable}' not found", 'warning');
          // Skip this relation if the target type doesn't exist
          unset($config['relations'][$relationTable]);
        }
      }

      // Recreate type with relations now that all types exist
      if (!empty($config['relations'])) {
        try {
          $this->types[$typeName] = $this->createTypeFromTable($table, $config);
        } catch (\Exception $e) {
          $this->logger->alert("RexQL: Error updating type '{$typeName}' with relations: " . $e->getMessage());
          throw $e;
        }
      }
    }

    // Third phase: Create queries
    foreach ($coreTables as $table => $config) {
      if (!in_array($table, $allowedTables)) {
        continue;
      }

      $typeName = $this->getTypeName($table);
      $this->log("RexQL: Creating queries for type '{$typeName}' (Phase 3)");

      try {
        $this->queries[$this->getQueryName($table)] = $this->createQueryField($table, $config, $typeName, false);
        $this->queries[$this->getListQueryName($table)] = $this->createQueryField($table, $config, $typeName);
      } catch (\Exception $e) {
        $this->logger->alert("RexQL: Error creating queries for type '{$typeName}': " . $e->getMessage());
        throw $e;
      }
    }
  }

  /**
   * Create YForm table types
   */
  private function buildYFormTypes(): void
  {
    if (!rex_addon::get('yform')->isAvailable()) {
      return;
    }

    $allowedTables = rex_addon::get('rexql')->getConfig('allowed_tables', []);
    $yformTables = rex_yform_manager_table::getAll();

    foreach ($yformTables as $table) {
      $tableName = $table->getTableName();

      if (!in_array($tableName, $allowedTables)) {
        continue;
      }

      $typeName = $this->getTypeName($tableName);
      $this->types[$typeName] = $this->createYFormType($table);
      $this->queries[$this->getQueryName($tableName)] = $this->createYFormQueryField($table, $typeName);
      $this->queries[$this->getListQueryName($tableName)] = $this->createYFormListQueryField($table, $typeName);
    }
  }

  /**
   * Create custom types
   */
  private function buildCustomTypes(): void
  {
    // Add system information
    $this->buildSystemTypes();

    // URL-Addon Integration
    if (rex_addon::get('url')->isAvailable()) {
      $this->buildUrlTypes();
    }

    // YRewrite-Addon Integration
    if (rex_addon::get('yrewrite')->isAvailable()) {
      $this->buildYRewriteTypes();
    }
  }

  /**
   * Create ObjectType from table definition
   */
  private function createTypeFromTable(string $table, array $tableConfig): ObjectType
  {

    $sql = rex_sql::factory();
    $sql->setQuery('DESCRIBE ' . $table);

    $fields = [];
    while ($sql->hasNext()) {
      $column = $sql->getValue('Field');
      $sqlType = $sql->getValue('Type');
      $type = $this->mapSqlTypeToGraphQL($sqlType);

      // Get description from configuration
      $description = $tableConfig['fields'][$column]['description'] ??
        ucfirst(str_replace('_', ' ', $column));

      $fields[$column] = [
        'type' => $type,
        'description' => $description,
        'resolve' => function ($root, $args, $context, $info) use ($column) {
          if (!is_array($root) || !isset($root[$column])) {
            return null;
          }
          return $root[$column];
        },
        // 'resolve' => [$this->resolver, 'resolveObjectType', $column]
      ];

      $sql->next();
    }

    // Add joined fields to GraphQL type
    if (!empty($tableConfig['joins'])) {
      foreach ($tableConfig['joins'] as $joinTable => $joinConfig) {
        if (!empty($joinConfig['fields'])) {
          foreach ($joinConfig['fields'] as $fieldAlias => $fieldExpression) {
            $fields[$fieldAlias] = [
              'type' => Type::string(), // Default to string, could be made more sophisticated
              'description' => "Joined field from {$joinTable}",
              'resolve' => function ($root, $args, $context) use ($fieldAlias) {
                try {
                  return isset($root[$fieldAlias]) ? (string) $root[$fieldAlias] : null;
                } catch (\Throwable $e) {
                  return null;
                }
              }
            ];
          }
        }
      }
    }

    // Add 1:n and n:1 relations
    if (!empty($tableConfig['relations'])) {
      foreach ($tableConfig['relations'] as $relationTable => $relationConfig) {
        $relationTypeName = $this->getTypeName($relationTable);

        // Use consistent naming: append 'List' for one-to-many relations
        $fieldName = $relationConfig['type'] === '1:n'
          ? lcfirst($relationTypeName) . 'List'  // One-to-many gets 'List' suffix
          : lcfirst($relationTypeName);          // Many-to-one stays singular

        $fields[$fieldName] = [
          'type' => function () use ($relationTable, $relationConfig) {
            $relationTypeName = $this->getTypeName($relationTable);
            switch ($relationConfig['type']) {
              case '1:n':
                // Return list of related records
                return isset($this->types[$relationTypeName]) ? Type::listOf($this->types[$relationTypeName]) : Type::listOf(Type::string());
              case 'n:1':
                // Return single related record
                return isset($this->types[$relationTypeName]) ? $this->types[$relationTypeName] : Type::string();
              default:
                return Type::string(); // Fallback
            }
          },
          'description' => "Related {$relationTable} records",
          'resolve' => function ($root) use ($relationTable, $relationConfig) {
            return $this->resolveRelation($root, $relationTable, $relationConfig);
          }
        ];
      }
    }

    if (empty($fields)) {
      throw new \Exception("No fields found for table {$table}");
    }

    return new ObjectType([
      'name' => $this->getTypeName($table),
      'description' => $tableConfig['description'] ?? "Tabelle {$table}",
      'fields' => $fields
    ]);
  }

  /**
   * Resolve relationship
   */
  private function resolveRelation(array $parentRecord, string $relationTable, array $relationConfig): array
  {
    $this->log("RexQL: Resolving relation for table '{$relationTable}'", 'debug');

    $isOneToMany = $relationConfig['type'] == '1:n';

    // Check if parent record has local key
    $localKey = $relationConfig['local_key'] ?? 'id';
    if (!isset($parentRecord[$localKey])) {
      return $isOneToMany ? [] : null;
    }
    if ($isOneToMany) {
      $orderBy = $relationConfig['order_by'] ?? 'id ASC';
      $limit = 0;
    } else {
      $orderBy = '';
      $limit = 1;
    }

    // Build query using shared helper
    $foreignId  = $parentRecord[$localKey];
    $foreignKey = $relationConfig['foreign_key'];
    $whereCondition = [":foreign_key" => $foreignId];
    $whereClause = "{$relationTable}.{$foreignKey} = :foreign_key";
    $queryData = $this->buildRelationQuery($relationTable, $whereCondition, $orderBy, $limit, $whereClause);

    $sql = rex_sql::factory();

    try {
      $sql->setQuery($queryData['query'], $queryData['params']);
      $result = $sql->getArray();

      $this->log("RexQL: Successfully resolved relation for table '{$relationTable}'", 'debug');
      if (empty($result)) {
        $this->log("RexQL: No related records found for table '{$relationTable}'", 'debug');
      }

      return $isOneToMany ? $result : (!empty($result) ? $result[0] : null);
    } catch (\Exception $e) {
      $this->logger->alert("RexQL: Error resolving relation for table '{$relationTable}': " . $e->getMessage());
      return [];
    }
  }

  /**
   * Create YForm ObjectType
   */
  private function createYFormType(rex_yform_manager_table $table): ObjectType
  {
    $fields = [];

    // Always add ID field (standard primary key)
    $fields['id'] = [
      'type' => Type::int(),
      'description' => 'Primary key'
    ];

    foreach ($table->getFields() as $field) {
      $fieldName = $field->getName();

      // Skip ID field as it was already added
      if ($fieldName === 'id') {
        continue;
      }

      $fieldType = $this->mapYFormTypeToGraphQL($field);

      $fields[$fieldName] = [
        'type' => $fieldType,
        'description' => $field->getLabel() ?: $fieldName
      ];
    }

    return new ObjectType([
      'name' => $this->getTypeName($table->getTableName()),
      'description' => $table->getDescription() ?: 'YForm Tabelle: ' . $table->getTableName(),
      'fields' => $fields
    ]);
  }

  /**
   * Create query field for single entry
   */
  private function createQueryField(string $table, array $tableConfig, string $typeName, bool $list = true): array
  {
    $defaultConfig = $this->getDefaultConfiguration();
    $args = $tableConfig['args'] ?? $defaultConfig['args'];

    // Add GraphQL types to arguments
    $graphqlArgs = [];
    foreach ($args as $key => $arg) {
      $graphqlArgs[$key] = [
        'type' => $this->mapStringToGraphQLType($arg['type'])
      ];
      if (isset($arg['defaultValue'])) {
        $graphqlArgs[$key]['defaultValue'] = $arg['defaultValue'];
      }
    }

    return [
      'type' => $list ? Type::listOf($this->types[$typeName]) : $this->types[$typeName],
      'args' => $graphqlArgs,
      'resolve' => function ($root, $args) use ($table, $list) {
        return $this->resolveRecords($table, $args, $list);
      }
    ];
  }

  /**
   * Create YForm query field for single entry
   */
  private function createYFormQueryField(rex_yform_manager_table $table, string $typeName): array
  {
    return [
      'type' => $this->types[$typeName],
      'args' => [
        'id' => ['type' => Type::nonNull(Type::int())],
      ],
      'resolve' => function ($root, $args) use ($table) {
        return $this->resolveYFormRecord($table, $args);
      }
    ];
  }

  /**
   * Create YForm query field for list
   */
  private function createYFormListQueryField(rex_yform_manager_table $table, string $typeName): array
  {
    return [
      'type' => Type::listOf($this->types[$typeName]),
      'args' => [
        'limit' => ['type' => Type::int(), 'defaultValue' => 10],
        'offset' => ['type' => Type::int(), 'defaultValue' => 0],
        'where' => ['type' => Type::string()],
        'order_by' => ['type' => Type::string(), 'defaultValue' => 'id DESC']
      ],
      'resolve' => function ($root, $args) use ($table) {
        return $this->resolveYFormRecords($table, $args);
      }
    ];
  }

  /**
   * Resolve list of records
   */
  private function resolveRecords(string $table, array $args, bool $list = true): array
  {
    $config = $this->getTableConfigurations()[$table] ?? [];

    // Debug logging
    $this->log("RexQL: Resolving records for table '{$table}'", 'debug');

    $sql = rex_sql::factory();
    $params = [];
    $where = '1=1';
    $order_by = $config['default_order'] ?? 'id DESC';

    // Build SELECT clause with joins
    $selectFields = [$table . '.*'];
    $joins = '';

    if (!empty($config['joins'])) {
      foreach ($config['joins'] as $joinTable => $joinConfig) {
        $joinType = $joinConfig['type'] ?? 'LEFT JOIN';
        $joinAlias = $joinConfig['alias'] ?? $joinTable;
        $joinOn = $joinConfig['on'];

        $joins .= " {$joinType} {$joinTable} AS {$joinAlias} ON {$joinOn}";

        // Add joined fields to SELECT
        if (!empty($joinConfig['fields'])) {
          foreach ($joinConfig['fields'] as $fieldAlias => $fieldExpression) {
            $selectFields[] = "{$fieldExpression} AS {$fieldAlias}";
          }
        }
      }
    }

    $selectClause = implode(', ', $selectFields);

    $argFields = ['id', 'article_id', 'clang_id', 'module_id', 'ctype_id', 'status'];
    foreach ($argFields as $field) {
      if (isset($args[$field])) {
        $where .= " AND {$table}.{$field} = :{$field}";
        $params[$field] = $args[$field];
      }
    }

    if (isset($args['where'])) {
      $where .= ' AND (' . $args['where'] . ')';
    }

    if (isset($args['order_by'])) {
      $order_by = $args['order_by'];
    }

    // // Pagination
    $limit = isset($args['limit']) ? (int) $args['limit'] : 100;
    $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

    // Build and execute query
    $query = "SELECT {$selectClause} FROM " . $table . $joins;
    $query .= " WHERE {$where}";
    $query .= " ORDER BY {$order_by}";
    $query .= " LIMIT {$limit} OFFSET {$offset}";

    $this->log("RexQL: Executing query: $query with params: " . json_encode($params), 'debug');


    try {
      $sql->setQuery($query, $params);
      $resultArray = $sql->getArray();
      $result = $list ? $resultArray : (!empty($resultArray) ? $resultArray[0] : null);

      if ($this->debugMode) {
        $count = $list ? count($resultArray) : (empty($resultArray) ? 0 : 1);
        $this->log("RexQL: Successfully resolved {$count} records for table '{$table}'", 'debug');
      }


      return $result;
    } catch (\Exception $e) {
      $this->logger->alert("RexQL: Error resolving records for table '{$table}': " . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Resolve single YForm record
   */
  private function resolveYFormRecord(rex_yform_manager_table $table, array $args): ?array
  {
    $dataset = $table->getRawDataset($args['id']);
    return $dataset ? $dataset->getData() : null;
  }

  /**
   * Resolve list of YForm records
   */
  private function resolveYFormRecords(rex_yform_manager_table $table, array $args): array
  {
    $query = $table->query();

    if (isset($args['where'])) {
      $query->whereRaw($args['where']);
    }

    $query->limit($args['limit'], $args['offset']);
    $query->orderBy('id', 'DESC');

    $collection = $query->find();
    $result = [];

    foreach ($collection as $dataset) {
      $result[] = $dataset->getData();
    }

    return $result;
  }

  /**
   * Map SQL type to GraphQL type
   */
  private function mapSqlTypeToGraphQL(string $sqlType): Type
  {
    $sqlType = strtolower($sqlType);

    // Enum must be checked before int, as enum values can contain 'int'
    if (str_contains($sqlType, 'enum(')) {
      return Type::string(); // Return enum as string
    }
    if (str_contains($sqlType, 'int')) {
      return Type::int();
    }
    if (str_contains($sqlType, 'decimal') || str_contains($sqlType, 'float') || str_contains($sqlType, 'double')) {
      return Type::float();
    }
    if (str_contains($sqlType, 'tinyint(1)') || str_contains($sqlType, 'boolean') || str_contains($sqlType, 'bool')) {
      return Type::boolean();
    }
    if (str_contains($sqlType, 'text') || str_contains($sqlType, 'varchar') || str_contains($sqlType, 'char')) {
      return Type::string();
    }
    if (str_contains($sqlType, 'datetime') || str_contains($sqlType, 'timestamp') || str_contains($sqlType, 'date') || str_contains($sqlType, 'time')) {
      return Type::string();
    }
    if (str_contains($sqlType, 'json')) {
      return Type::string(); // Return JSON as string
    }

    return Type::string(); // Fallback
  }

  /**
   * Map YForm field type to GraphQL type
   */
  private function mapYFormTypeToGraphQL(\rex_yform_manager_field $field): Type
  {
    $fieldType = $field->getType();
    $typeName = $field->getTypeName();

    switch ($fieldType) {
      case 'value':
        switch ($typeName) {
          case 'integer':
          case 'number':
            return Type::int();
          case 'float':
          case 'decimal':
            return Type::float();
          case 'checkbox':
            return Type::boolean();
          case 'date':
          case 'datetime':
          case 'time':
          case 'text':
          case 'textarea':
          case 'select':
          case 'radio':
          case 'email':
          case 'url':
          default:
            return Type::string();
        }
      case 'validate':
        // Validation fields usually return string
        return Type::string();
      case 'action':
        // Action fields are usually not relevant for GraphQL
        return Type::string();
      default:
        return Type::string();
    }
  }

  /**
   * Generate type name from table name
   */
  private function getTypeName(string $table): string
  {
    // Special handling for core tables
    $coreTypeNames = [
      'rex_config' => 'RexConfig',
      'rex_article' => 'RexArticle',
      'rex_article_slice' => 'RexArticleSlice',
      'rex_clang' => 'RexClang',
      'rex_media' => 'RexMedia'
    ];

    if (isset($coreTypeNames[$table])) {
      return $coreTypeNames[$table];
    }

    // Fallback for other tables
    $parts = explode('_', $table);
    $name = '';
    foreach ($parts as $part) {
      $name .= ucfirst($part);
    }
    return $name;
  }

  /**
   * Generate query name
   */
  private function getQueryName(string $table): string
  {
    return lcfirst($this->getTypeName($table));
  }

  /**
   * Generate list query name
   */
  private function getListQueryName(string $table): string
  {
    // Consistent naming: always "List" for list queries
    $baseName = lcfirst($this->getTypeName($table));
    return $baseName . 'List';
  }


  /**
   * Create URL types
   */
  private function buildUrlTypes(): void
  {
    // Add URL generator types
    $this->queries['urlByDataId'] = [
      'type' => Type::string(),
      'args' => [
        'data_id' => ['type' => Type::nonNull(Type::int())],
        'profile_id' => ['type' => Type::int()]
      ],
      'resolve' => function ($root, $args) {
        return $this->resolveUrlByDataId($args);
      }
    ];
  }

  /**
   * Create YRewrite types
   */
  private function buildYRewriteTypes(): void
  {
    $table = 'rex_yrewrite_domain';
    $typeName = $this->getTypeName($table);
    $config = [
      'description' => 'yrewrite Domain-Informationen',
      'fields' => [
        'domain' => ['description' => 'Domain'],
        'mount_id' => ['description' => 'Mount-ID'],
        'start_id' => ['description' => 'Start-ID'],
        'notfound_id' => ['description' => 'Not Found-ID'],
        'clang_start' => ['description' => 'Start-Sprache'],
      ]
    ];

    $this->types[$typeName] = $this->createTypeFromTable($table, $config);
    $this->queries[$this->getQueryName($table)] = [
      'type' => $this->types[$typeName],
      'args' => [
        'id' => ['type' => Type::int()],
        'where' => ['type' => Type::string()],
      ],
      'resolve' => function ($root, $args) use ($table) {
        return $this->resolveRecords($table, $args);
      }
    ];
  }

  /**
   * Resolve URL by Data-ID
   */
  private function resolveUrlByDataId(array $args): ?string
  {
    if (!rex_addon::get('url')->isAvailable()) {
      return null;
    }

    $sql = rex_sql::factory();
    $sql->setQuery(
      'SELECT url FROM rex_url_generator_url WHERE data_id = ? AND profile_id = ?',
      [$args['data_id'], $args['profile_id'] ?? 1]
    );

    return $sql->getRows() > 0 ? $sql->getValue('url') : null;
  }

  /**
   * Create system types
   */
  private function buildSystemTypes(): void
  {

    $yrewrite_domain_data = null;
    if (rex_addon::get('yrewrite')->isAvailable()) {
      \rex_yrewrite::init();
      $yrewrite_domain_data = \rex_yrewrite::getDefaultDomain();
    }

    // RexSystem Type erstellen
    $fields = [
      'server' => [
        'type' => Type::string(),
        'description' => 'Server-URL',
        'resolve' => function () {
          try {
            return \rex::getServer();
          } catch (\Throwable $e) {
            throw new \Exception('Error getting server: ' . $e->getMessage());
          }
        }
      ],
      'serverName' => [
        'type' => Type::string(),
        'description' => 'Server-Name',
        'resolve' => function () {
          try {
            return \rex::getServerName();
          } catch (\Throwable $e) {
            throw new \Exception('Error getting server name: ' . $e->getMessage());
          }
        }
      ],
      'errorEmail' => [
        'type' => Type::string(),
        'description' => 'Fehler-E-Mail-Adresse',
        'resolve' => function () {
          try {
            return \rex::getErrorEmail();
          } catch (\Throwable $e) {
            throw new \Exception('Error getting error email: ' . $e->getMessage());
          }
        }
      ],
      'version' => [
        'type' => Type::string(),
        'description' => 'REDAXO Version',
        'resolve' => function () {
          try {
            return \rex::getVersion();
          } catch (\Throwable $e) {
            throw new \Exception('Error getting version: ' . $e->getMessage());
          }
        }
      ],
      'startArticleId' => [
        'type' => Type::int(),
        'description' => 'Start-Artikel ID',
        'resolve' => function () {
          try {
            return \rex_addon::get('structure')->isAvailable()
              ? (int) \rex_config::get('structure', 'start_article_id', 1)
              : 1; // Return default value instead of null
          } catch (\Throwable $e) {
            throw new \Exception('Error getting start article ID: ' . $e->getMessage());
          }
        }
      ],
      'notFoundArticleId' => [
        'type' => Type::int(),
        'description' => 'Not-Found-Artikel ID',
        'resolve' => function () {
          try {
            return \rex_addon::get('structure')->isAvailable()
              ? (int) \rex_config::get('structure', 'notfound_article_id', 1)
              : 1; // Return default value instead of null
          } catch (\Throwable $e) {
            throw new \Exception('Error getting not found article ID: ' . $e->getMessage());
          }
        }
      ],
      'defaultTemplateId' => [
        'type' => Type::int(),
        'description' => 'Standard-Template ID',
        'resolve' => function () {
          try {
            return \rex_addon::get('structure')->isAvailable()
              ? (int) \rex_config::get('structure/content', 'default_template_id', 1)
              : 1; // Return default value instead of null
          } catch (\Throwable $e) {
            throw new \Exception('Error getting default template ID: ' . $e->getMessage());
          }
        }
      ],
      'domainHost' => [
        'type' => Type::string(),
        'description' => 'Domain Host',
        'resolve' => function () use ($yrewrite_domain_data) {
          try {
            return $yrewrite_domain_data ? $yrewrite_domain_data->getHost() : "";
          } catch (\Throwable $e) {
            throw new \Exception('Error getting domain host: ' . $e->getMessage());
          }
        }
      ],
      'domainUrl' => [
        'type' => Type::string(),
        'description' => 'Domain URL',
        'resolve' => function () use ($yrewrite_domain_data) {
          try {
            return $yrewrite_domain_data ? $yrewrite_domain_data->getUrl() : "";
          } catch (\Throwable $e) {
            throw new \Exception('Error getting domain url: ' . $e->getMessage());
          }
        }
      ],
      'domainStartId' => [
        'type' => Type::int(),
        'description' => 'Domain Start-Artikel-ID',
        'resolve' => function () use ($yrewrite_domain_data) {
          try {
            return $yrewrite_domain_data ? $yrewrite_domain_data->getStartId() : "";
          } catch (\Throwable $e) {
            throw new \Exception('Error getting domain start article ID: ' . $e->getMessage());
          }
        }
      ],
      'domainNotfoundId' => [
        'type' => Type::int(),
        'description' => 'Domain Not Found-Artikel-ID',
        'resolve' => function () use ($yrewrite_domain_data) {
          try {
            return $yrewrite_domain_data ? $yrewrite_domain_data->getNotfoundId() : "";
          } catch (\Throwable $e) {
            throw new \Exception('Error getting domain not found article ID' . $e->getMessage());
          }
        }
      ],
      'domainLanguages' => [
        'type' => Type::string(),
        'description' => 'Domain Sprachen',
        'resolve' => function () use ($yrewrite_domain_data) {
          try {
            if ($yrewrite_domain_data === null) {
              return [];
            }

            $allLanguages = $yrewrite_domain_data->getClangs();
            if (!count($allLanguages)) {
              $allLanguages = rex_clang::getAllIds();
            }
            $langs = [];
            foreach ($allLanguages as $clang) {
              $rex_clang = rex_clang::get($clang);
              $langs[] = [
                'id' => $rex_clang->getId(),
                'name' => $rex_clang->getName(),
                'code' => $rex_clang->getCode(),
              ];
            }
            return \json_encode($langs);
          } catch (\Throwable $e) {
            throw new \Exception('Error getting domain languages' . $e->getMessage());
          }
        }
      ],
      'domainDefaultLanguage' => [
        'type' => Type::string(),
        'description' => 'Domain Standard-Sprache',
        'resolve' => function () use ($yrewrite_domain_data) {
          try {
            if ($yrewrite_domain_data === null) {
              return [];
            }
            $rex_clang = rex_clang::get($yrewrite_domain_data->getStartClang());
            return \json_encode([
              'id' => $rex_clang->getId(),
              'name' => $rex_clang->getName(),
              'code' => $rex_clang->getCode(),
            ]);
          } catch (\Throwable $e) {
            throw new \Exception('Error getting domain default lang ID' . $e->getMessage());
          }
        }
      ]
    ];

    if (rex_addon::get('massif_settings')->isAvailable()) {
      $fields['addressData'] = [
        'type' => Type::string(),
        'description' => 'Stammdaten',
        'resolve' => function () {
          try {
            return \json_encode([
              'company' => rex_config::get('massif_settings', 'address_firma', ''),
              'companyCo' => rex_config::get('massif_settings', 'address_firma_zusatz', ''),
              'street' => rex_config::get('massif_settings', 'address_strasse', ''),
              'postalCode' => rex_config::get('massif_settings', 'address_plz', ''),
              'city' => rex_config::get('massif_settings', 'address_ort', ''),
              'region' => rex_config::get('massif_settings', 'address_kanton_code', ''),
              'country' => rex_config::get('massif_settings', 'address_land', ''),
              'countryCode' => rex_config::get('massif_settings', 'address_land_code', ''),
              'email' => rex_config::get('massif_settings', 'address_e-mail', ''),
              'phone' => rex_config::get('massif_settings', 'address_phone', ''),
              'lat' => rex_config::get('massif_settings', 'google_geo lat.', ''),
              'lng' => rex_config::get('massif_settings', 'google_geo lat.', ''),
              'maps_link' => rex_config::get('massif_settings', 'google_google_maps_link', ''),
              'instagram' => rex_config::get('massif_settings', 'social_instagram', ''),
              'facebook' => rex_config::get('massif_settings', 'social_facebook', ''),
              'twitter' => rex_config::get('massif_settings', 'social_twitter', ''),
              'youtube' => rex_config::get('massif_settings', 'social_youtube', ''),
              'linkedin' => rex_config::get('massif_settings', 'social_linkedin', ''),
              'xing' => rex_config::get('massif_settings', 'social_xing', ''),
            ]);
          } catch (\Throwable $e) {
            throw new \Exception('Error getting address data' . $e->getMessage());
          }
        }
      ];
    }
    $this->types['RexSystem'] = new ObjectType([
      'name' => 'RexSystem',
      'description' => 'REDAXO System-Informationen',
      'fields' => $fields
    ]);
    // Add rexSystem query
    $this->queries['rexSystem'] = [
      'type' => $this->types['RexSystem'],
      'description' => 'REDAXO System-Informationen abrufen',
      'resolve' => function () {
        // Return dummy object, the actual values are provided by the field resolvers
        return ['dummy' => true];
      }
    ];
  }

  /**
   * Shared method to build SELECT query with joins for relation resolution
   */
  private function buildRelationQuery(string $relationTable, array $params, string $orderBy = '', int $limit = 0, string $whereClause = ''): array
  {
    // Get table configuration for joins if they exist
    $tableConfigs = $this->getTableConfigurations();
    $config = $tableConfigs[$relationTable] ?? [];

    // Build SELECT clause with potential joins
    $selectFields = [$relationTable . '.*'];
    $joins = '';

    if (!empty($config['joins'])) {
      foreach ($config['joins'] as $joinTable => $joinConfig) {
        $joinType = $joinConfig['type'] ?? 'LEFT JOIN';
        $joinAlias = $joinConfig['alias'] ?? $joinTable;
        $joinOn = $joinConfig['on'];

        $joins .= " {$joinType} {$joinTable} AS {$joinAlias} ON {$joinOn}";

        // Add joined fields to SELECT
        if (!empty($joinConfig['fields'])) {
          foreach ($joinConfig['fields'] as $fieldAlias => $fieldExpression) {
            $selectFields[] = "{$fieldExpression} AS {$fieldAlias}";
          }
        }
      }
    }

    $selectClause = implode(', ', $selectFields);

    // Build the complete query
    $query = "SELECT {$selectClause} FROM {$relationTable}{$joins}";

    if (!empty($whereClause)) {
      $query .= " WHERE {$whereClause}";
    }

    if (!empty($orderBy)) {
      $query .= " ORDER BY {$orderBy}";
    }

    if ($limit > 0) {
      $query .= " LIMIT {$limit}";
    }

    return [
      'query' => $query,
      'params' => $params
    ];
  }

  /**
   * String-Typen in GraphQL-Typen umwandeln
   */
  private function mapStringToGraphQLType(string $type): Type
  {
    switch ($type) {
      case 'string':
        return Type::string();
      case 'int':
        return Type::int();
      case 'float':
        return Type::float();
      case 'boolean':
        return Type::boolean();
      case 'nonNull(string)':
        return Type::nonNull(Type::string());
      case 'nonNull(int)':
        return Type::nonNull(Type::int());
      default:
        return Type::string();
    }
  }
}
