<?php

/**
 * @file
 * Provide views data for entity_usage.module.
 */

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Implements hook_views_data().
 */
function entity_usage_views_data(): array {

  $data['entity_usage']['table']['group'] = t('Entity Usage');

  $data['entity_usage']['count'] = [
    'title' => t('Usage count'),
    'help' => t('How many times the target entity is referenced by the source entity.'),
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function entity_usage_views_data_alter(array &$data): void {

  $entity_types = \Drupal::entityTypeManager()->getDefinitions();

  // Provide a relationship for each entity type that has a base table.
  foreach ($entity_types as $type => $entity_type) {

    if (empty($data[$entity_type->getBaseTable()]) || !$entity_type->hasKey('id') || !$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      continue;
    }

    // Decide what column to use as base field depending on this entity type
    // "id" type.
    $id_key = $entity_type->getKey('id');
    /** @var \Drupal\Core\Field\BaseFieldDefinition $id_field */
    $id_field = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($type)[$id_key];
    $target_id_column = ($id_field->getType() === 'integer') ? 'target_id' : 'target_id_string';

    if ($data_table = $entity_type->getBaseTable()) {
      $data[$data_table][$type . '_to_usage_entity'] = [
        'title' => t('Information about the usage of this @entity_type', ['@entity_type' => $entity_type->getLabel()]),
        'help' => t('Creates a relationship about this <em>@entity_type</em> and the entity_usage information that relates to it.', ['@entity_type' => $entity_type->getLabel()]),
        'relationship' => [
          'base' => 'entity_usage',
          'base field' => $target_id_column,
          'field' => $entity_type->getKey('id'),
          'id' => 'standard',
          'label' => t('Usage information (@entity_type)', ['@entity_type' => $entity_type->getLabel()]),
          'extra' => [
            [
              'field' => 'target_type',
              'value' => $type,
            ],
          ],
        ],
      ];
    }
    elseif ($base_table = $entity_type->getDataTable()) {
      $data[$base_table][$type . '_to_usage_entity'] = [
        'title' => t('Information about the usage of this @entity_type', ['@entity_type' => $entity_type->getLabel()]),
        'help' => t('Creates a relationship about this <em>@entity_type</em> and the entity_usage information that relates to it.', ['@entity_type' => $entity_type->getLabel()]),
        'relationship' => [
          'base' => 'entity_usage',
          'base field' => $target_id_column,
          'field' => $entity_type->getKey('id'),
          'id' => 'standard',
          'label' => t('Usage information (@entity_type)', ['@entity_type' => $entity_type->getLabel()]),
          'extra' => [
            [
              'field' => 'target_type',
              'value' => $type,
            ],
          ],
        ],
      ];
    }

  }

}
