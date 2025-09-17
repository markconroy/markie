<?php

namespace Drupal\views\Hook;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for views.
 */
class ViewsViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['views']['table']['group'] = $this->t('Global');
    $data['views']['table']['join'] = ['#global' => []];
    $data['views']['random'] = [
      'title' => $this->t('Random'),
      'help' => $this->t('Randomize the display order.'),
      'sort' => [
        'id' => 'random',
      ],
    ];
    $data['views']['null'] = [
      'title' => $this->t('Null'),
      'help' => $this->t('Allow a contextual filter value to be ignored. The query will not be altered by this contextual filter value. Can be used when contextual filter values come from the URL, and a part of the URL needs to be ignored.'),
      'argument' => [
        'id' => 'null',
      ],
    ];
    $data['views']['nothing'] = [
      'title' => $this->t('Custom text'),
      'help' => $this->t('Provide custom text or link.'),
      'field' => [
        'id' => 'custom',
        'click sortable' => FALSE,
      ],
    ];
    $data['views']['counter'] = [
      'title' => $this->t('View result counter'),
      'help' => $this->t('Displays the actual position of the view result'),
      'field' => [
        'id' => 'counter',
      ],
    ];
    $data['views']['area'] = [
      'title' => $this->t('Text area'),
      'help' => $this->t('Provide markup for the area using any available text format.'),
      'area' => [
        'id' => 'text',
      ],
    ];
    $data['views']['area_text_custom'] = [
      'title' => $this->t('Unfiltered text'),
      'help' => $this->t('Provide markup for the area with minimal filtering.'),
      'area' => [
        'id' => 'text_custom',
      ],
    ];
    $data['views']['title'] = [
      'title' => $this->t('Title override'),
      'help' => $this->t('Override the default view title for this view. This is useful to display an alternative title when a view is empty.'),
      'area' => [
        'id' => 'title',
        'sub_type' => 'empty',
      ],
    ];
    $data['views']['view'] = [
      'title' => $this->t('View area'),
      'help' => $this->t('Insert a view inside an area.'),
      'area' => [
        'id' => 'view',
      ],
    ];
    $data['views']['result'] = [
      'title' => $this->t('Result summary'),
      'help' => $this->t('Shows result summary, for example the items per page.'),
      'area' => [
        'id' => 'result',
      ],
    ];
    $data['views']['messages'] = [
      'title' => $this->t('Messages'),
      'help' => $this->t('Displays messages in an area.'),
      'area' => [
        'id' => 'messages',
      ],
    ];
    $data['views']['http_status_code'] = [
      'title' => $this->t('Response status code'),
      'help' => $this->t('Alter the HTTP response status code used by this view, mostly helpful for empty results.'),
      'area' => [
        'id' => 'http_status_code',
      ],
    ];
    $data['views']['combine'] = [
      'title' => $this->t('Combine fields filter'),
      'help' => $this->t('Combine multiple fields together and search by them.'),
      'filter' => [
        'id' => 'combine',
      ],
    ];
    $data['views']['dropbutton'] = [
      'title' => $this->t('Dropbutton'),
      'help' => $this->t('Display fields in a dropbutton.'),
      'field' => [
        'id' => 'dropbutton',
      ],
    ];
    $data['views']['display_link'] = [
      'title' => $this->t('Link to display'),
      'help' => $this->t('Displays a link to a path-based display of this view while keeping the filter criteria, sort criteria, pager settings and contextual filters.'),
      'area' => [
        'id' => 'display_link',
      ],
    ];
    // Registers an entity area handler per entity type.
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      // Excludes entity types, which cannot be rendered.
      if ($entity_type->hasViewBuilderClass()) {
        $label = $entity_type->getLabel();
        $data['views']['entity_' . $entity_type_id] = [
          'title' => $this->t('Rendered entity - @label', [
            '@label' => $label,
          ]),
          'help' => $this->t('Displays a rendered @label entity in an area.', [
            '@label' => $label,
          ]),
          'area' => [
            'entity_type' => $entity_type_id,
            'id' => 'entity',
          ],
        ];
      }
    }
    // Registers an action bulk form per entity.
    $all_actions = \Drupal::entityTypeManager()->getStorage('action')->loadMultiple();
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type => $entity_info) {
      $actions = array_filter($all_actions, function (ActionConfigEntityInterface $action) use ($entity_type) {
          return $action->getType() == $entity_type;
      });
      if (empty($actions)) {
        continue;
      }
      $data[$entity_info->getBaseTable()][$entity_type . '_bulk_form'] = [
        'title' => $this->t('Bulk update'),
        'help' => $this->t('Allows users to apply an action to one or more items.'),
        'field' => [
          'id' => 'bulk_form',
        ],
      ];
    }
    // Registers views data for the entity itself.
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasHandlerClass('views_data')) {
        /** @var \Drupal\views\EntityViewsDataInterface $views_data */
        $views_data = \Drupal::entityTypeManager()->getHandler($entity_type_id, 'views_data');
        $data = NestedArray::mergeDeep($data, $views_data->getViewsData());
      }
    }
    // Field modules can implement hook_field_views_data() to override the
    // default behavior for adding fields.
    $module_handler = \Drupal::moduleHandler();
    $entity_type_manager = \Drupal::entityTypeManager();
    if ($entity_type_manager->hasDefinition('field_storage_config')) {
      /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
      foreach ($entity_type_manager->getStorage('field_storage_config')->loadMultiple() as $field_storage) {
        if (\Drupal::service('views.field_data_provider')->getSqlStorageForField($field_storage)) {
          $provider = $field_storage->getTypeProvider();
          $result = (array) $module_handler->invoke($provider === 'core' ? 'views' : $provider, 'field_views_data', [$field_storage]);
          if (empty($result)) {
            $result = \Drupal::service('views.field_data_provider')->defaultFieldImplementation($field_storage);
          }
          $module_handler->alter('field_views_data', $result, $field_storage);
          if (is_array($result)) {
            $data = NestedArray::mergeDeep($result, $data);
          }
          \Drupal::moduleHandler()->invoke($field_storage->getTypeProvider(), 'field_views_data_views_data_alter', [&$data, $field_storage]);
        }
      }
    }
    return $data;
  }

  /**
   * Implements hook_field_views_data().
   *
   * The function implements the hook on behalf of 'core' because it adds a
   * relationship and a reverse relationship to entity_reference field type,
   * which is provided by core. This function also provides an argument plugin
   * for entity_reference fields that handles title token replacement.
   */
  #[Hook('field_views_data')]
  public function fieldViewsData(FieldStorageConfigInterface $field_storage): array {
    $data = \Drupal::service('views.field_data_provider')->defaultFieldImplementation($field_storage);
    // The code below only deals with the Entity reference field type.
    if ($field_storage->getType() != 'entity_reference') {
      return $data;
    }
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type_id = $field_storage->getTargetEntityTypeId();
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $entity_type_manager->getStorage($entity_type_id)->getTableMapping();
    foreach ($data as $table_name => $table_data) {
      // Add a relationship to the target entity type.
      $target_entity_type_id = $field_storage->getSetting('target_type');
      $target_entity_type = $entity_type_manager->getDefinition($target_entity_type_id);
      $entity_type_id = $field_storage->getTargetEntityTypeId();
      $entity_type = $entity_type_manager->getDefinition($entity_type_id);
      $target_base_table = $target_entity_type->getDataTable() ?: $target_entity_type->getBaseTable();
      $field_name = $field_storage->getName();
      if ($target_entity_type instanceof ContentEntityTypeInterface) {
        // Provide a relationship for the entity type with the entity reference
        // field.
        $args = ['@label' => $target_entity_type->getLabel(), '@field_name' => $field_name];
        $data[$table_name][$field_name]['relationship'] = [
          'title' => $this->t('@label referenced from @field_name', $args),
          'label' => $this->t('@field_name: @label', $args),
          'group' => $entity_type->getLabel(),
          'help' => $this->t('Appears in: @bundles.', [
            '@bundles' => implode(', ', $field_storage->getBundles()),
          ]),
          'id' => 'standard',
          'base' => $target_base_table,
          'entity type' => $target_entity_type_id,
          'base field' => $target_entity_type->getKey('id'),
          'relationship field' => $field_name . '_target_id',
        ];
        // Provide a reverse relationship for the entity type that is referenced
        // by the field.
        $args['@entity'] = $entity_type->getLabel();
        $args['@label'] = $target_entity_type->getSingularLabel();
        $pseudo_field_name = 'reverse__' . $entity_type_id . '__' . $field_name;
        $data[$target_base_table][$pseudo_field_name]['relationship'] = [
          'title' => $this->t('@entity using @field_name', $args),
          'label' => $this->t('@field_name', [
            '@field_name' => $field_name,
          ]),
          'group' => $target_entity_type->getLabel(),
          'help' => $this->t('Relate each @entity with a @field_name set to the @label.', $args),
          'id' => 'entity_reverse',
          'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
          'entity_type' => $entity_type_id,
          'base field' => $entity_type->getKey('id'),
          'field_name' => $field_name,
          'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
          'field field' => $field_name . '_target_id',
          'join_extra' => [
                  [
                    'field' => 'deleted',
                    'value' => 0,
                    'numeric' => TRUE,
                  ],
          ],
        ];
      }
      // Provide an argument plugin that has a meaningful titleQuery()
      // implementation getting the entity label.
      $data[$table_name][$field_name . '_target_id']['argument']['id'] = 'entity_target_id';
      $data[$table_name][$field_name . '_target_id']['argument']['target_entity_type_id'] = $target_entity_type_id;
    }
    return $data;
  }

}
