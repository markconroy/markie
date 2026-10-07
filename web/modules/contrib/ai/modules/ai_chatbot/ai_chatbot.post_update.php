<?php

/**
 * @file
 * Contains post_update hooks for ai_chatbot module.
 */

use Drupal\block\BlockInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\ai_chatbot\Plugin\Block\DeepChatFormBlock;

/**
 * Migrate DeepChat blocks to the ChatProcessor plugin approach.
 *
 * Moves the legacy assistant settings (ai_assistant, stream, verbose_mode,
 * show_structured_results) into the ai_assistant_api_processor plugin
 * configuration.
 */
function ai_chatbot_post_update_chat_processor(&$sandbox): void {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (BlockInterface $block) {
    if ($block->getPluginId() !== 'ai_deepchat_block') {
      return FALSE;
    }
    $settings = $block->get('settings');
    $changed = FALSE;

    // Point the block at the ChatProcessor plugin that wraps the AI
    // Assistant API, carrying over the previously selected assistant,
    // streaming option and verbosity.
    if (empty($settings['chat_processor_plugin'])) {
      $settings['chat_processor_plugin'] = 'ai_assistant_api_processor';
      $settings['plugin_configuration'] = [
        'assistant_id' => $settings['ai_assistant'] ?? '',
        'stream_output' => (bool) ($settings['stream'] ?? FALSE),
        'verbose_mode' => (bool) ($settings['verbose_mode'] ?? DeepChatFormBlock::LEGACY_VERBOSE_MODE),
        'show_structured_results' => (bool) ($settings['show_structured_results'] ?? FALSE),
      ];
      $changed = TRUE;
    }
    // The assistant is now referenced from the plugin configuration, so
    // drop the stale block-level setting.
    if (isset($settings['ai_assistant'])) {
      unset($settings['ai_assistant']);
      $changed = TRUE;
    }
    // Verbosity now lives in the plugin configuration, so drop the
    // block-level setting.
    if (isset($settings['verbose_mode'])) {
      unset($settings['verbose_mode']);
      $changed = TRUE;
    }
    // Structured results now live in the plugin configuration. Carry the
    // block-level value over (also for blocks that already point at the
    // processor plugin) and drop the stale block-level setting.
    if (isset($settings['show_structured_results'])) {
      unset($settings['show_structured_results']);
      $changed = TRUE;
    }
    if ($changed) {
      // Blocks that predate the introduction of use_username/use_avatar (and
      // legacy blocks whose stream value was never explicitly saved) are
      // missing these keys entirely. When $block->set() below re-instantiates
      // the block plugin, its defaultConfiguration() would otherwise fill the
      // gaps in with PHP booleans, while the schema declares these as
      // integers. ConfigEntityUpdater saves with trustData() (skipping the
      // normal cast-on-save step), so normalize them explicitly here.
      foreach (['use_username', 'use_avatar', 'stream'] as $key) {
        $settings[$key] = (int) ($settings[$key] ?? 0);
      }
      $block->set('settings', $settings);
      return TRUE;
    }
    return FALSE;
  };

  $config_entity_updater->update($sandbox, 'block', $callback);
}
