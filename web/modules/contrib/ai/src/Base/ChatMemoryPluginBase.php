<?php

namespace Drupal\ai\Base;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface;

/**
 * Base class for ChatMemory plugins.
 *
 * This base class provides configuration and form boilerplate for ChatMemory
 * plugins, plus a default appendMessages() implementation based on
 * loadMessages() and saveMessages().
 *
 * The configuration boilerplate is implemented here rather than inherited
 * from \Drupal\Core\Plugin\ConfigurablePluginBase or
 * \Drupal\Core\Plugin\ConfigurableTrait: both were only added in Drupal
 * 11.3, while this module supports ^10.5 || ^11.2. The semantics are
 * identical to the core trait, so plugins behave the same on every
 * supported core version.
 */
abstract class ChatMemoryPluginBase extends PluginBase implements ChatMemoryInterface {

  /**
   * Constructs a ChatMemory plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeepArray([$this->defaultConfiguration(), $configuration], TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendMessages(string $thread_id, array $messages): void {
    foreach ($messages as $message) {
      if (!$message instanceof ChatMessage) {
        throw new \InvalidArgumentException('Messages must be instances of ChatMessage.');
      }
    }
    $this->saveMessages($thread_id, array_merge($this->loadMessages($thread_id), $messages));
  }

  /**
   * {@inheritdoc}
   */
  public function clearMessages(string $thread_id): void {
    $this->saveMessages($thread_id, []);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPersistentThread(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

}
