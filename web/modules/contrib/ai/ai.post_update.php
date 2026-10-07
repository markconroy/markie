<?php

/**
 * @file
 * Contains post update hooks for ai module.
 */

use Drupal\ai\Guardrail\AiGuardrailEntityInterface;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Re-calculate dependencies for all guardrail sets.
 */
function ai_post_update_13001(&$sandbox) {
  // Resave all guardrail sets to re-calculate its dependencies.
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (AiGuardrailSetInterface $guardrail_set) {
    return TRUE;
  };

  $config_entity_updater->update($sandbox, 'ai_guardrail_set', $callback);
}

/**
 * Add scan_all_user_messages default to existing guardrail config entities.
 */
function ai_post_update_13002(): void {
  $target_plugins = ['regexp_guardrail', 'restrict_to_topic'];
  $storage = \Drupal::entityTypeManager()->getStorage('ai_guardrail');

  /** @var \Drupal\ai\Entity\AiGuardrail[] $guardrails */
  $guardrails = $storage->loadMultiple();

  foreach ($guardrails as $guardrail) {
    // Only add the setting to plugins that actually support it.
    if (!in_array($guardrail->get('guardrail'), $target_plugins, TRUE)) {
      continue;
    }

    $settings = $guardrail->get('guardrail_settings');
    if (is_array($settings) && !isset($settings['scan_all_user_messages'])) {
      $settings['scan_all_user_messages'] = FALSE;
      $guardrail->set('guardrail_settings', $settings);
      $guardrail->save();
    }
  }
}

/**
 * Add the global_guardrails key to ai.settings for existing installations.
 *
 * @see https://www.drupal.org/project/ai/issues/3584851
 */
function ai_post_update_14001() {
  $config = \Drupal::configFactory()->getEditable('ai.settings');
  if ($config->get('global_guardrails') === NULL) {
    $config->set('global_guardrails', [])->save(TRUE);
  }
}

/**
 * Add matching_mode and similarity_threshold to RestrictToTopic guardrails.
 */
function ai_post_update_15001(&$sandbox): void {
  // Resave all guardrails that use the restrict_to_topic plugin so the change
  // is propagated through the config entity (and its dependencies) rather than
  // the raw config object.
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (AiGuardrailEntityInterface $guardrail) {
    if ($guardrail->get('guardrail') !== 'restrict_to_topic') {
      return FALSE;
    }
    $settings = $guardrail->get('guardrail_settings') ?? [];
    if (isset($settings['matching_mode'], $settings['similarity_threshold'])) {
      return FALSE;
    }
    $settings += [
      'matching_mode' => 'exact',
      'similarity_threshold' => 0.75,
    ];
    $guardrail->set('guardrail_settings', $settings);
    return TRUE;
  };

  $config_entity_updater->update($sandbox, 'ai_guardrail', $callback);
}

/**
 * Rename check_all_messages to scan_all_user_messages for InputLengthLimit.
 */
function ai_post_update_15002(&$sandbox): void {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (AiGuardrailEntityInterface $guardrail) {
    if ($guardrail->get('guardrail') !== 'input_length_limit') {
      return FALSE;
    }
    $settings = $guardrail->get('guardrail_settings') ?? [];
    if (!array_key_exists('check_all_messages', $settings)) {
      return FALSE;
    }
    $settings['scan_all_user_messages'] = $settings['check_all_messages'];
    unset($settings['check_all_messages']);
    $guardrail->set('guardrail_settings', $settings);
    return TRUE;
  };

  $config_entity_updater->update($sandbox, 'ai_guardrail', $callback);
}
