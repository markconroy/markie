<?php

/**
 * @file
 * Contains post update hooks for ai_content_suggestions.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Use field widget action plugin instead of hook for AI buttons.
 */
function ai_content_suggestions_post_update_10001(&$sandbox = NULL) {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (EntityFormDisplayInterface $form_display) {
    $needs_save = FALSE;
    $components = $form_display->getComponents();
    foreach ($components as $name => $component) {
      if (!empty($component['third_party_settings']['ai_content_suggestions'])) {
        $former_settings = $component['third_party_settings']['ai_content_suggestions'];
        $button = '';
        if (isset($former_settings['button'])) {
          $button = $former_settings['button'];
          unset($former_settings['button']);
        }
        $component['third_party_settings']['field_widget_actions'] = [];
        $uuid = \Drupal::service('uuid')->generate();
        $component['third_party_settings']['field_widget_actions'][$uuid] = [
          'plugin_id' => 'prompt_content_suggestion',
          'enabled' => $former_settings['enabled'],
          'weight' => 0,
          'button_label' => $button,
          'settings' => $former_settings['settings'],
        ];
        unset($component['third_party_settings']['ai_content_suggestions']);
        $form_display->setComponent($name, $component);
        $needs_save = TRUE;
      }
    }
    return $needs_save;
  };

  $config_entity_updater->update($sandbox, 'entity_form_display', $callback);
}

/**
 * Move plugin configuration to ai_content_suggestions.settings config object.
 */
function ai_content_suggestions_post_update_15001(&$sandbox = NULL) {
  $config = \Drupal::configFactory()->getEditable('ai_content_suggestions.settings');
  $prompt_config = \Drupal::configFactory()->getEditable('ai_content_suggestions.prompts');
  $plugins = $config->get('plugins');
  $plugins_config = [];
  foreach ($plugins as $id => $model) {
    if (is_array($model)) {
      $plugins_config[$id] = $model;
      continue;
    }
    $plugins_config[$id] = [
      'enabled' => TRUE,
      'model' => $model,
    ];
    $prompt = $prompt_config->get($id);
    if ($prompt) {
      $plugins_config[$id]['prompt'] = $prompt;
    }
  }
  if (!empty($plugins_config['taxonomy_suggest'])) {
    $plugins_config['taxonomy_suggest']['prompt_open'] = $prompt_config->get('taxonomy_suggest_open');
    $plugins_config['taxonomy_suggest']['prompt_from_voc'] = $prompt_config->get('taxonomy_suggest_from_voc');
  }
  $tone_config = \Drupal::configFactory()->getEditable('ai_content_suggestions.tone');
  if (!empty($plugins_config['tone'])) {
    $plugins_config['tone']['taxonomy'] = $tone_config->get('tone_taxonomy');
    $plugins_config['tone']['taxonomy_enabled'] = $tone_config->get('tone_taxonomy_enabled');
  }
  $config->set('plugins', $plugins_config);
  $config->save(TRUE);
  // Delete not used config objects.
  $tone_config->delete();
  $prompt_config->delete();
}
