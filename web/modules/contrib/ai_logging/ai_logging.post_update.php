<?php

/**
 * @file
 * Post update functions for AI Logging.
 */

/**
 * Converts prompt_logging_output to boolean and removes unused bundles key.
 */
function ai_logging_post_update_fix_logging_config(&$sandbox) {
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('ai_logging.settings');

  // Remove the unused 'prompt_logging_bundles' key.
  $config->clear('prompt_logging_bundles');

  // Convert 'prompt_logging_output' to a strict boolean.
  $output_setting = $config->get('prompt_logging_output');
  $config->set('prompt_logging_output', (bool) $output_setting);
  $config->save();

  return t('Fixed AI Logging configuration types and removed unused keys.');
}

/**
 * Adds the prompt_logging_excluded_tags setting to existing configuration.
 */
function ai_logging_post_update_add_excluded_tags_setting(&$sandbox) {
  $config = \Drupal::configFactory()->getEditable('ai_logging.settings');

  // Sites that upgraded from the AI Logging submodule bundled with AI core
  // 1.3.x/1.4.x may already carry a value; leave it untouched.
  if ($config->get('prompt_logging_excluded_tags') !== NULL) {
    return t('The AI Logging excluded request tags setting was already present.');
  }

  $config->set('prompt_logging_excluded_tags', '')->save();
  return t('Added the AI Logging excluded request tags setting.');
}

/**
 * Adds the conversation thread settings to existing configuration.
 */
function ai_logging_post_update_add_thread_settings(&$sandbox) {
  $config = \Drupal::configFactory()->getEditable('ai_logging.settings');
  if ($config->get('thread_tag_prefixes') === NULL) {
    $config->set('thread_tag_prefixes', [
      'ai_agents_thread_',
      'ai_assistant_thread_',
    ]);
  }
  if ($config->get('thread_primary_tag_pattern') === NULL) {
    $config->set('thread_primary_tag_pattern', 'ai_agents_prompt_%');
  }
  $config->save();
  return t('Added the AI Logging conversation thread settings.');
}
