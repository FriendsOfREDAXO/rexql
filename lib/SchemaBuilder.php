<?php

namespace FriendsOfRedaxo\RexQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use rex_addon;
use rex_sql;
use rex_yform_manager_table;
use rex_logger;
use rex_config;
use rex_clang;

/**
 * GraphQL Schema Builder für REDAXO
 */
class SchemaBuilder
{
  private array $types = [];
  private array $queries = [];
  private array $mutations = [];

  /**
   * Tabellen-Konfigurationen für Argumente und spezielle Behandlung
   */
  private function getTableConfigurations(): array
  {
    return [
      'rex_article_slice' => [
        'description' => 'REDAXO Artikel-Slices',
        'single_args' => [
          'id' => ['type' => 'int'],
          'article_id' => ['type' => 'int'],
          'clang_id' => ['type' => 'int', 'defaultValue' => 1],
          'module_id' => ['type' => 'int'],
          'ctype_id' => ['type' => 'int'],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'priority ASC']
        ],
        'list_args' => [
          'article_id' => ['type' => 'int'],
          'clang_id' => ['type' => 'int'],
          'module_id' => ['type' => 'int'],
          'ctype_id' => ['type' => 'int'],
          'limit' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'priority ASC']
        ],
        'fields' => [
          'article_id' => ['description' => 'Article ID'],
          'clang_id' => ['description' => 'Language ID'],
          'module_id' => ['description' => 'Module ID'],
          'ctype_id' => ['description' => 'Content type ID'],
          'priority' => ['description' => 'Priority/order'],
          'value1' => ['description' => 'Module value 1'],
          'value2' => ['description' => 'Module value 2']
        ]
      ],
      'rex_article' => [
        'description' => 'REDAXO Artikel',
        'single_args' => [
          'id' => ['type' => 'int'],
          'status' => ['type' => 'int', 'defaultValue' => 1],
          'clang_id' => ['type' => 'int', 'defaultValue' => 1],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
        ],
        'list_args' => [
          'limit' => ['type' => 'int'],
          'status' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'clang_id' => ['type' => 'int'],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
        ],
        'fields' => [
          'name' => ['description' => 'Article name'],
          'status' => ['description' => 'Article status'],
          'clang_id' => ['description' => 'Language ID'],
          'parent_id' => ['description' => 'Parent article ID']
        ]
      ],
      'rex_structure' => [
        'description' => 'REDAXO Struktur',
        'single_args' => [
          'id' => ['type' => 'int'],
          'status' => ['type' => 'int', 'defaultValue' => 1],
          'clang_id' => ['type' => 'int', 'defaultValue' => 1],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
        ],
        'list_args' => [
          'limit' => ['type' => 'int'],
          'status' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'clang_id' => ['type' => 'int'],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
        ],
        'fields' => [
          'name' => ['description' => 'Structure name'],
          'status' => ['description' => 'Structure status'],
          'clang_id' => ['description' => 'Language ID'],
          'parent_id' => ['description' => 'Parent structure ID']
        ]
      ],
      // Default configuration for other tables
      '_default' => [
        'description' => 'Database table',
        'single_args' => [
          'id' => ['type' => 'int'],
          'status' => ['type' => 'int', 'defaultValue' => 1],
          'clang_id' => ['type' => 'int', 'defaultValue' => 1],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
        ],
        'list_args' => [
          'limit' => ['type' => 'int'],
          'status' => ['type' => 'int'],
          'offset' => ['type' => 'int', 'defaultValue' => 0],
          'clang_id' => ['type' => 'int'],
          'where' => ['type' => 'string'],
          'order_by' => ['type' => 'string', 'defaultValue' => 'id DESC']
        ],
        'fields' => []
      ]
    ];
  }

  /**
   * Vollständiges GraphQL Schema erstellen
   */
  public function buildSchema(): Schema
  {
    // Debug logging für Schema-Building
    if (rex_addon::get('rexql')->getConfig('debug_mode', false)) {
      rex_logger::factory()->info('RexQL: Building fresh GraphQL schema');
    }

    $this->buildCoreTypes();
    $this->buildYFormTypes();
    $this->buildCustomTypes();

    return new Schema([
      'query' => new ObjectType([
        'name' => 'Query',
        'fields' => $this->queries
      ]),
      'mutation' => new ObjectType([
        'name' => 'Mutation',
        'fields' => $this->mutations
      ]),
      'types' => array_values($this->types)
    ]);
  }

  /**
   * Core-Tabellen-Types erstellen
   */
  private function buildCoreTypes(): void
  {
    $allowedTables = rex_addon::get('rexql')->getConfig('allowed_tables', []);
    $coreTables = $this->getCoreTableConfig();

    foreach ($coreTables as $table => $config) {
      if (!in_array($table, $allowedTables)) {
        continue;
      }

      $typeName = $this->getTypeName($table);
      if (rex_addon::get('rexql')->getConfig('debug_mode', false)) {
        rex_logger::factory()->info("RexQL: Creating type '{$typeName}' for table '{$table}'");
      }
      $this->types[$typeName] = $this->createTypeFromTable($table, $config);
      $this->queries[$this->getQueryName($table)] = $this->createQueryField($table, $typeName);
      $this->queries[$this->getListQueryName($table)] = $this->createListQueryField($table, $typeName);
    }
  }

  /**
   * YForm-Tabellen-Types erstellen
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
   * Benutzerdefinierte Types erstellen
   */
  private function buildCustomTypes(): void
  {
    // System-Informationen hinzufügen
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
   * ObjectType aus Tabellen-Definition erstellen
   */
  private function createTypeFromTable(string $table, array $config): ObjectType
  {
    $sql = rex_sql::factory();
    $sql->setQuery('DESCRIBE ' . $table);

    $configurations = $this->getTableConfigurations();
    $tableConfig = $configurations[$table] ?? $configurations['_default'];

    $fields = [];
    while ($sql->hasNext()) {
      $column = $sql->getValue('Field');
      $sqlType = $sql->getValue('Type');
      $type = $this->mapSqlTypeToGraphQL($sqlType);

      // Beschreibung aus Konfiguration holen
      $description = $tableConfig['fields'][$column]['description'] ??
        $config['fields'][$column]['description'] ??
        ucfirst(str_replace('_', ' ', $column));

      $fields[$column] = [
        'type' => $type,
        'description' => $description,
        'resolve' => function ($root, $args, $context, $info) use ($column) {
          if (!is_array($root) || !isset($root[$column])) {
            return null;
          }
          return $root[$column];
        }
      ];

      $sql->next();
    }

    if (empty($fields)) {
      throw new \Exception("No fields found for table {$table}");
    }

    return new ObjectType([
      'name' => $this->getTypeName($table),
      'description' => $tableConfig['description'] ?? $config['description'] ?? "Tabelle {$table}",
      'fields' => $fields
    ]);
  }

  /**
   * YForm ObjectType erstellen
   */
  private function createYFormType(rex_yform_manager_table $table): ObjectType
  {
    $fields = [];

    // ID-Feld immer hinzufügen (Standard-Primärschlüssel)
    $fields['id'] = [
      'type' => Type::int(),
      'description' => 'Primärschlüssel'
    ];

    foreach ($table->getFields() as $field) {
      $fieldName = $field->getName();

      // ID-Feld überspringen, da es bereits hinzugefügt wurde
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
   * Query-Field für Einzeleintrag erstellen
   */
  private function createQueryField(string $table, string $typeName): array
  {
    $configurations = $this->getTableConfigurations();
    $config = $configurations[$table] ?? $configurations['_default'];
    $args = $config['single_args'] ?? [];

    // GraphQL Typen zu den Argumenten hinzufügen
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
      'type' => $this->types[$typeName],
      'args' => $graphqlArgs,
      'resolve' => function ($root, $args) use ($table) {
        return $this->resolveRecord($table, $args);
      }
    ];
  }

  /**
   * Query-Field für Liste erstellen
   */
  private function createListQueryField(string $table, string $typeName): array
  {
    $configurations = $this->getTableConfigurations();
    $config = $configurations[$table] ?? $configurations['_default'];
    $args = $config['list_args'] ?? [];

    // GraphQL Typen zu den Argumenten hinzufügen
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
      'type' => Type::listOf($this->types[$typeName]),
      'args' => $graphqlArgs,
      'resolve' => function ($root, $args) use ($table) {
        return $this->resolveRecords($table, $args);
      }
    ];
  }

  /**
   * YForm Query-Field für Einzeleintrag erstellen
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
   * YForm Query-Field für Liste erstellen
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
   * Einzelnen Datensatz auflösen
   */
  private function resolveRecord(string $table, array $args): ?array
  {
    $sql = rex_sql::factory();
    $where = 'WHERE 1=1';
    $params = [];

    // Spezielle Behandlung für rex_config
    if ($table === 'rex_config') {
      if (isset($args['namespace'])) {
        $where .= ' AND namespace = :namespace';
        $params['namespace'] = $args['namespace'];
      }
      if (isset($args['key'])) {
        $where .= ' AND `key` = :key';
        $params['key'] = $args['key'];
      }
    } elseif ($table === 'rex_article_slice') {
      // Spezielle Behandlung für rex_article_slice
      if (isset($args['id'])) {
        $where .= ' AND id = :id';
        $params['id'] = $args['id'];
      }
      if (isset($args['article_id'])) {
        $where .= ' AND article_id = :article_id';
        $params['article_id'] = $args['article_id'];
      }
      if (isset($args['clang_id'])) {
        $where .= ' AND clang_id = :clang_id';
        $params['clang_id'] = $args['clang_id'];
      }
      if (isset($args['module_id'])) {
        $where .= ' AND module_id = :module_id';
        $params['module_id'] = $args['module_id'];
      }
      if (isset($args['ctype_id'])) {
        $where .= ' AND ctype_id = :ctype_id';
        $params['ctype_id'] = $args['ctype_id'];
      }
    } else {
      // Standard-Tabellen mit ID
      if (isset($args['id'])) {
        $where .= ' AND id = :id';
        $params['id'] = $args['id'];
      }

      if (isset($args['clang_id'])) {
        $where .= ' AND clang_id = :clang_id';
        $params['clang_id'] = $args['clang_id'];
      }

      if (isset($args['status'])) {
        $where .= ' AND status = :status';
        $params['status'] = $args['status'];
      }
    }

    if (isset($args['where'])) {
      $where .= ' AND (' . $args['where'] . ')';
    }

    $sql->setQuery("SELECT * FROM $table $where LIMIT 1", $params);

    return $sql->getRows() > 0 ? $sql->getArray()[0] : null;
  }

  /**
   * Liste von Datensätzen auflösen
   */
  private function resolveRecords(string $table, array $args): array
  {
    $sql = rex_sql::factory();
    $where = 'WHERE 1=1';
    $order_by = 'id DESC';
    $params = [];

    // Debug logging
    if (rex_addon::get('rexql')->getConfig('debug_mode', false)) {
      rex_logger::factory()->debug("RexQL: Resolving records for table '{$table}' with args: " . json_encode($args));
    }

    // Spezielle Behandlung für rex_config
    if ($table === 'rex_config') {
      if (isset($args['namespace'])) {
        $where .= ' AND namespace = :namespace';
        $params['namespace'] = $args['namespace'];
      }
      if (isset($args['key'])) {
        $where .= ' AND `key` = :key';
        $params['key'] = $args['key'];
      }
      $order_by = $args['order_by'] ?? 'namespace ASC, `key` ASC';
    } elseif ($table === 'rex_article_slice') {
      // Spezielle Behandlung für rex_article_slice
      if (isset($args['article_id'])) {
        $where .= ' AND article_id = :article_id';
        $params['article_id'] = $args['article_id'];
      }
      if (isset($args['clang_id'])) {
        $where .= ' AND clang_id = :clang_id';
        $params['clang_id'] = $args['clang_id'];
      }
      if (isset($args['module_id'])) {
        $where .= ' AND module_id = :module_id';
        $params['module_id'] = $args['module_id'];
      }
      if (isset($args['ctype_id'])) {
        $where .= ' AND ctype_id = :ctype_id';
        $params['ctype_id'] = $args['ctype_id'];
      }
      $order_by = $args['order_by'] ?? 'priority ASC';
    } else {
      // Standard-Tabellen
      if (isset($args['clang_id'])) {
        $where .= ' AND clang_id = :clang_id';
        $params['clang_id'] = $args['clang_id'];
      }

      if (isset($args['status'])) {
        $where .= ' AND status = :status';
        $params['status'] = $args['status'];
      }

      if (isset($args['order_by'])) {
        $order_by = $args['order_by'];
      }
    }

    if (isset($args['where'])) {
      $where .= ' AND (' . $args['where'] . ')';
    }

    $limit = '';
    if (isset($args['limit'])) {
      if (!isset($args['offset'])) {
        $args['offset'] = 0; // Default offset if not provided
      }
      $limit = 'LIMIT ' . $args['offset'] . ', ' . $args['limit'];
    } else {
      // Set default limit for rex_config to prevent memory issues
      if ($table === 'rex_config') {
        $limit = 'LIMIT 0, 50'; // Default limit of 50 for rex_config
      }
    }

    $queryString = "SELECT * FROM $table $where ORDER BY $order_by $limit";

    if (rex_addon::get('rexql')->getConfig('debug_mode', false)) {
      rex_logger::factory()->debug("RexQL: Executing query: $queryString with params: " . json_encode($params));
    }

    $sql->setQuery($queryString, $params);

    try {
      $result = $sql->getArray();

      if (rex_addon::get('rexql')->getConfig('debug_mode', false)) {
        rex_logger::factory()->debug("RexQL: Successfully resolved " . count($result) . " records for table '{$table}'");
      }

      return $result;
    } catch (\Exception $e) {
      if (rex_addon::get('rexql')->getConfig('debug_mode', false)) {
        rex_logger::factory()->error("RexQL: Error resolving records for table '{$table}': " . $e->getMessage());
      }
      throw $e;
    }
  }

  /**
   * Einzelnen YForm-Datensatz auflösen
   */
  private function resolveYFormRecord(rex_yform_manager_table $table, array $args): ?array
  {
    $dataset = $table->getRawDataset($args['id']);
    return $dataset ? $dataset->getData() : null;
  }

  /**
   * Liste von YForm-Datensätzen auflösen
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
   * SQL-Type zu GraphQL-Type mappen
   */
  private function mapSqlTypeToGraphQL(string $sqlType): Type
  {
    $sqlType = strtolower($sqlType);

    // Enum muss vor int geprüft werden, da enum-Werte 'int' enthalten können
    if (str_contains($sqlType, 'enum(')) {
      return Type::string(); // Enum als String zurückgeben
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
      return Type::string(); // JSON als String zurückgeben
    }

    return Type::string(); // Fallback
  }

  /**
   * YForm-Field-Type zu GraphQL-Type mappen
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
        // Validierungsfelder geben meist String zurück
        return Type::string();
      case 'action':
        // Action-Felder sind meist nicht relevant für GraphQL
        return Type::string();
      default:
        return Type::string();
    }
  }

  /**
   * Type-Name aus Tabellennamen generieren
   */
  private function getTypeName(string $table): string
  {
    // Spezielle Behandlung für Core-Tabellen
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

    // Fallback für andere Tabellen
    $parts = explode('_', $table);
    $name = '';
    foreach ($parts as $part) {
      $name .= ucfirst($part);
    }
    return $name;
  }

  /**
   * Query-Name generieren
   */
  private function getQueryName(string $table): string
  {
    return lcfirst($this->getTypeName($table));
  }

  /**
   * List-Query-Name generieren
   */
  private function getListQueryName(string $table): string
  {
    // Konsistente Namensgebung: immer "List" für Listen-Queries
    $baseName = lcfirst($this->getTypeName($table));
    return $baseName . 'List';
  }

  /**
   * Core-Tabellen-Konfiguration
   */
  private function getCoreTableConfig(): array
  {
    return [
      'rex_article' => [
        'description' => 'REDAXO Artikel',
        'fields' => [
          'id' => ['description' => 'Artikel-ID'],
          'name' => ['description' => 'Artikel-Name'],
          'clang_id' => ['description' => 'Sprach-ID']
        ]
      ],
      'rex_article_slice' => [
        'description' => 'REDAXO Artikel-Inhalte',
        'fields' => [
          'id' => ['description' => 'Slice-ID'],
          'article_id' => ['description' => 'Artikel-ID'],
          'module_id' => ['description' => 'Modul-ID']
        ]
      ],
      'rex_clang' => [
        'description' => 'REDAXO Sprachen',
        'fields' => [
          'id' => ['description' => 'Sprach-ID'],
          'name' => ['description' => 'Sprach-Name'],
          'code' => ['description' => 'Sprach-Code']
        ]
      ],
      'rex_media' => [
        'description' => 'REDAXO Medien',
        'fields' => [
          'id' => ['description' => 'Media-ID'],
          'filename' => ['description' => 'Dateiname'],
          'title' => ['description' => 'Titel']
        ]
      ],
      'rex_media_category' => [
        'description' => 'REDAXO Medien-Kategorien',
        'fields' => [
          'id' => ['description' => 'Media-Category-ID'],
          'name' => ['description' => 'Name'],
          'parent_id' => ['description' => 'Eltern-ID der Kategorie']
        ]
      ],
      'rex_module' => [
        'description' => 'REDAXO Module',
        'fields' => [
          'id' => ['description' => 'Module-ID'],
          'key' => ['description' => 'Key'],
          'name' => ['description' => 'Name'],
        ]
      ],
      'rex_template' => [
        'description' => 'REDAXO Templates',
        'fields' => [
          'id' => ['description' => 'Template-ID'],
          'key' => ['description' => 'Key'],
          'name' => ['description' => 'Name'],
        ]
      ]


    ];
  }

  /**
   * URL-Types erstellen
   */
  private function buildUrlTypes(): void
  {
    // URL-Generator Types hinzufügen
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
   * YRewrite-Types erstellen
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
        return $this->resolveRecord($table, $args);
      }
    ];
  }

  /**
   * URL anhand Data-ID auflösen
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
   * System-Types erstellen
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
    // rexSystem Query hinzufügen
    $this->queries['rexSystem'] = [
      'type' => $this->types['RexSystem'],
      'description' => 'REDAXO System-Informationen abrufen',
      'resolve' => function () {
        // Dummy-Objekt zurückgeben, die eigentlichen Werte werden von den Feld-Resolvern geliefert
        return ['dummy' => true];
      }
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
