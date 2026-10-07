<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Traits;

use Drupal\ai_automators\AiAutomatorInterface;
use Drupal\ai_automators\Entity\AiAutomator;

/**
 * Helper methods for creating AiAutomator configuration for use in tests.
 */
trait AiAutomatorCreationTrait {

  /**
   * Creates and saves an AI Automator for use in automated tests.
   *
   * Plugin configuration is derived from the merged entity values and then
   * merged with the second argument so tests can override individual plugin
   * keys (for example "automator_llm_media_type") without repeating the full
   * structure.
   *
   * If the "plugin_config" key is present in $values, it is merged after the
   * derived defaults and before $pluginConfig.
   *
   * @param array $values
   *   Config values to set on the AI Automator config entity.
   * @param array $pluginConfig
   *   Plugin configuration to apply in the `plugin_config`.
   *
   * @return \Drupal\ai_automators\AiAutomatorInterface
   *   The saved automator.
   */
  protected function createAiAutomator(array $values = [], array $pluginConfig = []): AiAutomatorInterface {
    // Apply default configuration.
    $values += [
      'id' => 'ai_automators.kernel_test.default',
      'label' => 'Kernel test automator',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'body',
      'worker_type' => 'direct',
      'rule' => 'llm_text_long',
      'input_mode' => 'base',
      'weight' => 100,
      'edit_mode' => FALSE,
      'base_field' => '',
      'prompt' => '',
      'token' => '',
    ];

    // If the plugin configuration is included in the main values, use it.
    $pluginFromValues = $values['plugin_config'] ?? [];
    unset($values['plugin_config']);

    $derivedPlugin = [
      'automator_enabled' => 1,
      'automator_rule' => $values['rule'],
      'automator_mode' => $values['input_mode'],
      'automator_base_field' => $values['base_field'] ?? '',
      'automator_prompt' => $values['prompt'] ?? '',
      'automator_token' => $values['token'] ?? '',
      'automator_edit_mode' => !empty($values['edit_mode']) ? 1 : 0,
      'automator_label' => $values['label'],
      'automator_weight' => (string) $values['weight'],
      'automator_worker_type' => $values['worker_type'],
      'automator_ai_provider' => 'echoai',
      'automator_ai_model' => 'default',
    ];

    $values['plugin_config'] = array_merge($derivedPlugin, $pluginFromValues, $pluginConfig);

    /** @var \Drupal\ai_automators\AiAutomatorInterface $automator */
    $automator = AiAutomator::create($values);
    $automator->save();

    return $automator;
  }

}
