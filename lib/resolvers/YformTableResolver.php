<?php

namespace FriendsOfRedaxo\RexQL\Resolver;


use FriendsOfRedaxo\RexQL\Resolver\ResolverBase;
use FriendsOfRedaxo\RexQL\Utility;
use GraphQL\Type\Definition\Type;

use rex_addon;
use rex_extension;
use rex_extension_point;
use rex_yform_manager_collection;
use rex_yform_manager_dataset;
use rex_yform_manager_table;
use rex_yform_manager_field;

use function rex_getUrl;

class YformTableResolver extends ResolverBase
{
  static protected $yformTables = [];

  protected array $datasetRelations = [];
  protected array $tableFields = [];
  protected bool $isUrlAvailable = false;


  public function getData(): array
  {

    $redaxo_url = rex_addon::get('url');
    $this->isUrlAvailable = $redaxo_url->isAvailable();

    $isCollection = str_ends_with($this->typeName, 'Collection');
    $this->typeName = $isCollection ? str_replace('Collection', '', $this->typeName) : str_replace('Dataset', '', $this->typeName);

    /**
     * @var rex_yform_manager_table $table
     */
    $table = self::$yformTables[$this->typeName]['name'] ?? null;
    if (!$table || !($table instanceof rex_yform_manager_table)) {
      $this->error('YForm table not found: ' . $this->typeName);
    }
    $this->table = $table->getTableName();

    // Get table fields and field selection
    $this->tableFields = self::$yformTables[$this->typeName]['fields'] ?? [];
    $fieldSelection = $this->info->getFieldSelection(5);
    $this->fields = $this->getFields($this->typeName, $fieldSelection);

    // Prepare dataset relations
    foreach ($this->tableFields as $fieldName => $field) {
      if ($field['orgType'] === 'be_manager_relation') {
        $this->datasetRelations[$fieldName] = $field['orgName'];
      }
    }

    $data = [];

    if ($isCollection) {
      /**
       * @var rex_yform_manager_query $query
       */
      $query = $table->query();
      if (isset($this->args['status'])) {
        $query->where('status', $this->args['status'] ? 1 : 0);
      } else {
        $query->where('status', 1); // Default to only active items
      }
      if (isset($this->args['orderBy'])) {
        $query->resetOrderBy()->orderByRaw($this->args['orderBy']);
      }
      if (isset($this->args['where'])) {
        $where = "({$this->args['where']})";
        if (isset($this->args['status']) && $this->args['status'] === true) {
          $where .= " AND {$query->getTableAlias()}`status` = 1";
        }
        $query->whereRaw($where);
      }
      if (isset($this->args['limit'])) {
        $offset = $this->args['offset'] ?? 0;
        $query->limit($offset, $this->args['limit']);
      }
      /**
       * @var rex_yform_manager_collection $results
       */
      $results = $query->find();
      foreach ($this->datasetRelations as $fieldName => $relation) {
        if (isset($this->fields[$this->typeName][$fieldName])) {
          $query->populateRelation($relation);
        }
      }
      foreach ($results as $dataset) {
        $item = $this->populateData($dataset);
        $data[] = $item;
      }
    } else {
      /**
       * @var rex_yform_manager_dataset $dataset
       */
      $dataset = rex_yform_manager_dataset::get($this->args['id'], $this->table);
      if (!$dataset) {
        $this->error('YForm dataset not found: ' . $this->args['id']);
      }
      $item = $this->populateData($dataset);
      $data = $item;
    }

    return $data;
  }

  protected function populateData(rex_yform_manager_dataset $dataset): array
  {
    $item = [];
    foreach ($this->fields[$this->typeName] as $field) {
      if (isset($this->tableFields[$field])) {
        $fieldType = $this->tableFields[$field]['orgType'];
        if ($dataset->hasValue($field)) {
          $this->log("Processing field: $field with type: $fieldType");
          switch ($fieldType) {
            case 'id':
              $item['id'] = $dataset->getId();
              break;
            case 'be_manager_relation':
              $relatedCollection = $dataset->getRelatedCollection($this->datasetRelations[$field]);
              $data = [];
              foreach ($relatedCollection as $relatedDataset) {
                $datasetFields = $relatedDataset->getFields();
                foreach ($datasetFields as $datasetField) {
                  if (in_array($datasetField->getType(), ['validate', 'action'])) {
                    continue; // Skip validate and action fields
                  }
                  $data[$datasetField->getName()] = $relatedDataset->getValue($datasetField->getName());
                }
              }
              $this->log("Processing be_manager_relation field: $field " . json_encode($data, JSON_PRETTY_PRINT));
              $item[$field] = json_encode($data);
              break;
            default:
              $item[$field] = $dataset->getValue($field);
              break;
          }
        } else {
          switch ($field) {
            case 'slug':
              $slug = '';
              if ($this->isUrlAvailable) {
                if ($this->args['slugNamespace'] ?? null) {
                  $slug = rex_getUrl(null, null, [$this->args['slugNamespace'] => $dataset->getId()]);
                }
                if (!$slug) {
                  $profiles = \Url\Profile::getByTableName($this->table);
                  $profile = $profiles ? reset($profiles) : null;
                  if ($profile) {
                    $slug = rex_getUrl(null, null, [$profile->getNamespace() => $dataset->getId()]);
                  }
                }
              } else if ($this->args['slugNamespace'] ?? null) {
                $slug = rex_getUrl(null, null, [$this->args['slugNamespace'] => $this->table, 'id' => $dataset->getId()]);
              }
              $item['slug'] = $slug;
              break;
          }
        }
      }
    }
    return $item;
  }

  public static function registerResolvers(): void
  {
    $instance = new self();

    rex_extension::register('REXQL_EXTEND', function (rex_extension_point $ep) use ($instance) {
      $sdl = '';
      $sdlEntries = [];
      $yformTables = rex_yform_manager_table::getAll();
      foreach ($yformTables as $key => $table) {
        $tableName = $table->getTableName();
        $tableTypeName = Utility::snakeCaseToCamelCase($tableName);

        $sdlEntries[$key] = [
          'queries' => [
            0 => $tableTypeName . 'Dataset(id: ID!, slugNamespace: String): ' . $tableTypeName,
            1 => $tableTypeName . 'Collection(status: Boolean, where: String, orderBy: String, offset: Int, limit: Int, slugNamespace: String): [' . $tableTypeName . ']',
          ],
          'type' => $tableTypeName,
          'fields' => [],
        ];
        $fields = $table->getFields();

        $sdlEntries[$key]['fields']['id'] = ['type' => Type::id(), 'orgType' => 'integer', 'orgName' => 'id']; // Ensure 'id' is always present
        $sdlEntries[$key]['fields']['slug'] = ['type' => Type::string(), 'orgType' => 'text', 'orgName' => 'slug']; // Ensure 'slug' is always present

        foreach ($fields as $field) {
          $fieldTypeId = $field->getType();
          $orgFieldType = $field->getTypeName();
          if (in_array($fieldTypeId, ['validate', 'action'])) {
            continue; // Skip validate and action fields
          }
          $fieldName = $field->getName();

          $mappedFieldType = $instance->mapYFormTypeToGraphQL($orgFieldType, $fieldTypeId);
          $fieldTypeName = Utility::snakeCaseToCamelCase($fieldName);
          if ($fieldTypeName === 'type') {
            $fieldTypeName = 'typeName'; // Avoid conflict with GraphQL reserved keyword
          }
          $sdlEntries[$key]['fields'][$fieldTypeName] = ['type' => $mappedFieldType, 'orgType' => $orgFieldType, 'orgName' => $fieldName];
          $extensions['rootResolvers']['query'][$tableTypeName . 'Dataset'] = $instance->resolve();
          $extensions['rootResolvers']['query'][$tableTypeName . 'Collection'] = $instance->resolve();
        }
        self::$yformTables[$tableTypeName] = [
          'name' => $table,
          'fields' => $sdlEntries[$key]['fields'],
        ];
      }

      foreach ($sdlEntries as $key => $entry) {
        foreach ($entry['queries'] as $query) {
          $sdl .= "extend type Query {\n  " . $query . "\n}\n\n";
        }
        $sdl .= "type " . $entry['type'] . " {\n";
        foreach ($entry['fields'] as $fieldName => $field) {
          $sdl .= "  " . $fieldName . ": " . $field['type'] . "\n";
        }
        $sdl .= "}\n\n";
      }

      $extensions['sdl'] = $sdl;
      $ep->setSubject($extensions);
    }, rex_extension::LATE);
  }

  /**
   * Map YForm field type to GraphQL type
   */
  private function mapYFormTypeToGraphQL(string $typeName, string $fieldType): Type
  {
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
      default:
        return Type::string();
    }
  }
}
