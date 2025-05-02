<?php

/**
 * @file
 * Post update functions for the Entity Usage module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function entity_usage_removed_post_updates(): array {
  return [
    'entity_usage_post_update_regenerate_2x' => '8.x-2.0',
  ];
}

/**
 * Clean up entity usage regenerate queue.
 */
function entity_usage_post_update_clean_up_regenerate_queue(array &$sandbox): void {
  $queue = \Drupal::queue('entity_usage_regenerate_queue');
  if ($queue->numberOfItems() > 0) {
    $queue->deleteQueue();
    \Drupal::messenger()->addWarning('There were unprocessed items in the entity_usage_regenerate_queue. Queue processing is no longer an option for the entity-usage:recreate command. Please re-run the command without the --use-queue flag, or visit the UI and trigger the batch update there.');
  }
}

/**
 * Rebuild the container to add new services for Entity Usage module.
 */
function entity_usage_post_update_add_pre_save_url_recorder_service(array &$sandbox): void {
  // Empty update to force container rebuild.
}

/**
 * Remove unsupported source entity types from config.
 */
function entity_usage_post_update_remove_unsupported_source_entity_types(array &$sandbox): void {
  /** @var \Drupal\entity_usage\EntityUsageTrackManager $plugin_manager */
  $plugin_manager = \Drupal::service('plugin.manager.entity_usage.track');
  $entity_type_manager = \Drupal::service('entity_type.manager');
  $config = \Drupal::configFactory()->getEditable('entity_usage.settings');

  // Remove any entity types that are not supported.
  $source_entity_types = $config->get('track_enabled_source_entity_types') ?? [];
  $updated_entity_types = [];
  foreach ($source_entity_types as $entity_type_id) {
    if ($entity_type_manager->hasDefinition($entity_type_id) && $plugin_manager->isEntityTypeSource($entity_type_manager->getDefinition($entity_type_id))) {
      $updated_entity_types[] = $entity_type_id;
    }
  }

  if ($source_entity_types !== $updated_entity_types) {
    $config
      ->set('track_enabled_source_entity_types', $updated_entity_types)
      ->save();
  }
}
