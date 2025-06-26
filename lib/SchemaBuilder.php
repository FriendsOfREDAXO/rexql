<?php

namespace FriendsOfRedaxo\RexQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use rex_addon;
use rex_sql;
use rex_yform_manager_table;

/**
 * GraphQL Schema Builder für REDAXO
 */
class SchemaBuilder
{
  private array $types = [];
  private array $queries = [];
  private array $mutations = [];

  /**
   * Vollständiges GraphQL Schema erstellen
   */
  public function buildSchema(): Schema
  {
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

    $fields = [];
    while ($sql->hasNext()) {
      $column = $sql->getValue('Field');
      $type = $this->mapSqlTypeToGraphQL($sql->getValue('Type'));

      $fields[$column] = [
        'type' => $type,
        'description' => $config['fields'][$column]['description'] ?? null
      ];

      $sql->next();
    }

    return new ObjectType([
      'name' => $this->getTypeName($table),
      'description' => $config['description'] ?? null,
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
    return [
      'type' => $this->types[$typeName],
      'args' => [
        'id' => ['type' => Type::nonNull(Type::int())],
        'clang_id' => ['type' => Type::int()]
      ],
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
    return [
      'type' => Type::listOf($this->types[$typeName]),
      'args' => [
        'limit' => ['type' => Type::int(), 'defaultValue' => 10],
        'offset' => ['type' => Type::int(), 'defaultValue' => 0],
        'clang_id' => ['type' => Type::int()],
        'where' => ['type' => Type::string()]
      ],
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
        'where' => ['type' => Type::string()]
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
    $where = 'id = :id';
    $params = ['id' => $args['id']];

    if (isset($args['clang_id'])) {
      $where .= ' AND clang_id = :clang_id';
      $params['clang_id'] = $args['clang_id'];
    }

    $sql->setQuery("SELECT * FROM $table WHERE $where", $params);

    return $sql->getRows() > 0 ? $sql->getArray()[0] : null;
  }

  /**
   * Liste von Datensätzen auflösen
   */
  private function resolveRecords(string $table, array $args): array
  {
    $sql = rex_sql::factory();
    $where = '1=1';
    $params = [];

    if (isset($args['clang_id'])) {
      $where .= ' AND clang_id = :clang_id';
      $params['clang_id'] = $args['clang_id'];
    }

    if (isset($args['where'])) {
      $where .= ' AND (' . $args['where'] . ')';
    }

    $limit = 'LIMIT ' . $args['offset'] . ', ' . $args['limit'];

    $sql->setQuery("SELECT * FROM $table WHERE $where ORDER BY id DESC $limit", $params);

    return $sql->getArray();
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
    if (str_contains($sqlType, 'int')) {
      return Type::int();
    }
    if (str_contains($sqlType, 'decimal') || str_contains($sqlType, 'float')) {
      return Type::float();
    }
    if (str_contains($sqlType, 'tinyint(1)')) {
      return Type::boolean();
    }
    if (str_contains($sqlType, 'text') || str_contains($sqlType, 'varchar')) {
      return Type::string();
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
    return str_replace(['rex_', '_'], ['', ''], ucwords($table, '_'));
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
    // Domain-spezifische Queries hinzufügen
    $this->queries['domainByHost'] = [
      'type' => $this->types['YrewriteDomain'] ?? Type::string(),
      'args' => [
        'host' => ['type' => Type::nonNull(Type::string())]
      ],
      'resolve' => function ($root, $args) {
        return $this->resolveDomainByHost($args);
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
   * Domain anhand Host auflösen
   */
  private function resolveDomainByHost(array $args): ?array
  {
    if (!rex_addon::get('yrewrite')->isAvailable()) {
      return null;
    }

    $sql = rex_sql::factory();
    $sql->setQuery('SELECT * FROM rex_yrewrite_domain WHERE domain = ?', [$args['host']]);

    return $sql->getRows() > 0 ? $sql->getArray()[0] : null;
  }
}
