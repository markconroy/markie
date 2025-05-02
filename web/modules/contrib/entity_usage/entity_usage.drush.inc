<?php

/**
 * @file
 * Defines drush commands for the Entity Usage module.
 */

/**
 * Implements hook_drush_command().
 */
function entity_usage_drush_command(): array {
  $items = [];

  $items['entity-usage-recreate'] = [
    'description' => 'Recreate all entity usage statistics.',
    'aliases' => ['eu-r'],
  ];

  return $items;
}

/**
 * Command callback for 'entity-usage-recreate'.
 */
function drush_entity_usage_recreate(): void {
  \Drupal::service('entity_usage.batch_manager')->recreate();
  drush_backend_batch_process();
}
