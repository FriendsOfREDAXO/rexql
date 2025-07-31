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

  public function getData(): array|null
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

    $this->fields = array_keys($fieldSelection);

    // Prepare dataset relations
    foreach ($this->tableFields as $fieldName => $field) {
      if (!isset($fieldSelection[$fieldName])) {
        continue; // Skip fields not in selection
      }
      if ($field['orgType'] === 'be_manager_relation') {
        $this->datasetRelations[$fieldName] = ['name' => $field['orgName'], 'relatedTypename' => $field['relatedTypename'], 'relatedTablename' => $field['relatedTablename'], 'relationType' => $field['relationType'], 'fields' => array_keys($fieldSelection[$fieldName])];
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
        if (isset($this->fields[$fieldName])) {
          $results->populateRelation($relation['name']);
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
      $data = $item ?? null;
    }

    return $data;
  }

  protected function populateData(rex_yform_manager_dataset $dataset): array
  {
    $item = [];

    foreach ($this->fields as $subTypename => $field) {
      $fieldName = !is_array($field) ? $field : $subTypename;

      if (isset($this->tableFields[$fieldName])) {
        $fieldType = $this->tableFields[$fieldName]['orgType'];

        if ($dataset->hasValue($fieldName)) {

          switch ($fieldType) {
            case 'id':
              $item['id'] = $dataset->getId();
              break;
            case 'be_manager_relation':
              $this->checkPermissions($this->tableFields[$fieldName]['relatedTypename']);
              if ($this->datasetRelations[$fieldName]['relationType'] === 'single') {
                $relatedDataset = $dataset->getRelatedDataset($this->datasetRelations[$fieldName]['name']);
                if (!$relatedDataset) {
                  $item[$fieldName] = null;
                  break;
                }
                $fields = $this->datasetRelations[$fieldName]['fields'];
                $data = [];
                foreach ($fields as $relatedField) {
                  if ($relatedDataset->hasValue($relatedField)) {
                    $data[$relatedField] = $relatedDataset->getValue($relatedField);
                  }
                }
                $item[$fieldName] = $data;
              } else {
                $relatedCollection = $dataset->getRelatedCollection($this->datasetRelations[$fieldName]['name']);
                if (!$relatedCollection || !$relatedCollection->count()) {
                  $item[$fieldName] = [];
                  break;
                }
                $fields = $this->datasetRelations[$fieldName]['fields'];
                foreach ($relatedCollection as $relatedDataset) {
                  $data = [];
                  foreach ($fields as $relatedField) {
                    if ($relatedDataset->hasValue($relatedField)) {
                      $data[$relatedField] = $relatedDataset->getValue($relatedField);
                    }
                  }
                  $item[$fieldName][] = $data;
                }
              }
              break;
            default:
              $item[$fieldName] = $dataset->getValue($fieldName);
              break;
          }
        } else {
          switch ($fieldName) {
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
        $fields = $table->getValueFields();

        $sdlEntries[$key]['fields']['id'] = ['type' => Type::id(), 'orgType' => 'integer', 'orgName' => 'id']; // Ensure 'id' is always present
        $sdlEntries[$key]['fields']['slug'] = ['type' => Type::string(), 'orgType' => 'text', 'orgName' => 'slug']; // Ensure 'slug' is always present

        /**
         * @var rex_yform_manager_field $field
         */
        foreach ($fields as $field) {
          $fieldTypeId = $field->getType();
          $orgFieldType = $field->getTypeName();
          $fieldName = $field->getName();

          $mappedFieldType = $instance->mapYFormTypeToGraphQL($orgFieldType, $fieldTypeId);
          $fieldTypeName = Utility::snakeCaseToCamelCase($fieldName);
          if ($fieldTypeName === 'type') {
            $fieldTypeName = 'typeName'; // Avoid conflict with GraphQL reserved keyword
          }
          $relatedTablename = $orgFieldType === 'be_manager_relation' ? $field->getElement('table') : null;
          $relatedTypename = $relatedTablename ? Utility::snakeCaseToCamelCase($relatedTablename) : null;
          $relatedRelation = $relatedTablename ? (int)$field->getElement('type') : 0;
          $relationType = $relatedRelation === 0 || $relatedRelation === 2 ? 'single' : 'multiple';

          $sdlEntries[$key]['fields'][$fieldTypeName] = ['type' => $mappedFieldType, 'orgType' => $orgFieldType, 'orgName' => $fieldName, 'relatedTablename' => $relatedTablename, 'relatedTypename' => $relatedTypename, 'relationType' => $relationType];
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
          if ($field['relatedTypename'] ?? null) {
            if ($field['relationType'] === 'multiple') {
              $sdl .= "  " . $fieldName . ": [" . $field['relatedTypename'] . "]!\n";
            } else {
              $sdl .= "  " . $fieldName . ": " . $field['relatedTypename'] . "\n";
            }
          } else {
            $sdl .= "  " . $fieldName . ": " . $field['type'] . "\n";
          }
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

  public function checkPermissions(string $typeName): bool
  {
    $this->log('Checking permissions for type: ' . $typeName);

    $hasPermission = $this->context->hasPermission($typeName);
    if (!$hasPermission) {
      $this->error("You do not have permission to access {$typeName}.");
    }
    return true;
  }
}
