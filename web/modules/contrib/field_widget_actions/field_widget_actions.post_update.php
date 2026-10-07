<?php

/**
 * @file
 * Contains post update hooks for field_widget_actions module.
 */

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Ensure valid uuids for field widget actions in form displays.
 */
function field_widget_actions_post_update_ensure_valid_uuids(&$sandbox): void {
  // Resave all entity form displays that contain invalid field widget actions
  // identifiers to make sure that each button has valid UUID.
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (EntityFormDisplayInterface $entity_form_display) {
    foreach ($entity_form_display->getComponents() as $component) {
      if (!empty($component['third_party_settings']['field_widget_actions']) && is_array($component['third_party_settings']['field_widget_actions'])) {
        foreach ($component['third_party_settings']['field_widget_actions'] as $key => $value) {
          // If at least 1 key is not valid uuid, resave entity form display.
          if (!Uuid::isValid($key)) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  };

  $config_entity_updater->update($sandbox, 'entity_form_display', $callback);
}
