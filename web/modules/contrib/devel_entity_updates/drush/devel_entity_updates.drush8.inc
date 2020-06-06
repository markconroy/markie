<?php

/**
 * @file
 * Drush8 commands definitions.
 */

use Drupal\Core\Entity\EntityStorageException;
use Drupal\devel_entity_updates\DevelEntityDefinitionUpdateManager;
use Drush\Log\LogLevel;

/**
 * Implements hook_drush_command().
 */
function devel_entity_updates_drush_command() {
  $items = [];

  $items['devel-entity-updates'] = [
    'description' => dt('Apply pending entity schema updates (development tool).'),
    'aliases' => ['dentup', 'entup'],
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'core' => ['8+'],
  ];

  return $items;
}

/**
 * Implements hook_drush_command_alter().
 */
function devel_entity_updates_drush_command_alter(&$command) {
  if ($command['command'] === 'entity-updates') {
    $commands = drush_get_commands();
    $command = $commands['devel-entity-updates'];
    drush_set_command($command);
  }
}

/**
 * Command handler. Apply pending entity schema updates.
 */
function drush_devel_entity_updates() {
  if (drush_get_context('DRUSH_SIMULATE')) {
    drush_log(dt('devel-entity-updates command does not support --simulate option.'), LogLevel::OK);
  }

  drush_include_engine('drupal', 'update');
  if (devel_entity_updates_main() === FALSE) {
    return FALSE;
  }

  drush_drupal_cache_clear_all();
  drush_log(dt('Finished performing updates.'), LogLevel::OK);
  return NULL;
}

/**
 * Apply pending entity schema updates.
 */
function devel_entity_updates_main() {
  $result = TRUE;
  $change_summary = \Drupal::entityDefinitionUpdateManager()->getChangeSummary();

  if (!empty($change_summary)) {
    drush_print(dt('The following updates are pending:'));
    drush_print();

    foreach ($change_summary as $entity_type_id => $changes) {
      drush_print($entity_type_id . ' entity type : ');
      foreach ($changes as $change) {
        drush_print(strip_tags($change), 2);
      }
    }

    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      return drush_user_abort();
    }

    try {
      \Drupal::classResolver()
        ->getInstanceFromDefinition(DevelEntityDefinitionUpdateManager::class)
        ->applyUpdates();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('update', $e);
    }
  }
  else {
    drush_log(dt("No entity schema updates required"), LogLevel::SUCCESS);
    $result = FALSE;
  }

  return $result;
}
