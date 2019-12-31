<?php

namespace Drupal\Tests\field_group\Functional;

use Drupal;

/**
 * Provides common functionality for the FieldGroup test classes.
 */
trait FieldGroupTestTrait {

  /**
   * Create a new group.
   *
   * @param string $entity_type
   *   The entity type as string.
   * @param string $bundle
   *   The bundle of the enity type.
   * @param string $context
   *   The context for the group.
   * @param string $mode
   *   The view/form mode.
   * @param array $data
   *   Data for the field group.
   *
   * @return \stdClass
   *   An object that represents the field group.
   */
  protected function createGroup($entity_type, $bundle, $context, $mode, array $data) {

    if (!isset($data['format_settings'])) {
      $data['format_settings'] = [];
    }

    $data['format_settings'] += Drupal::service('plugin.manager.field_group.formatters')->getDefaultSettings($data['format_type'], $context);

    $group_name = 'group_' . mb_strtolower($this->randomMachineName());

    $field_group = (object) [
      'group_name' => $group_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'mode' => $mode,
      'context' => $context,
      'children' => isset($data['children']) ? $data['children'] : [],
      'parent_name' => isset($data['parent']) ? $data['parent'] : '',
      'weight' => isset($data['weight']) ? $data['weight'] : 0,
      'label' => isset($data['label']) ? $data['label'] : $this->randomString(8),
      'format_type' => $data['format_type'],
      'format_settings' => $data['format_settings'],
      'region' => 'content',
    ];

    field_group_group_save($field_group);

    return $field_group;
  }

}
