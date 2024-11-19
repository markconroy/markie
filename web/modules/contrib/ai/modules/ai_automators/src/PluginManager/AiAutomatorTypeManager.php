<?php

namespace Drupal\ai_automators\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an OpenAI Automator Type plugin manager.
 *
 * @see \Drupal\ai_automators\Attribute\AiAutomatorType
 * @see \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface
 * @see plugin_api
 */
class AiAutomatorTypeManager extends DefaultPluginManager {

  /**
   * Constructs a AiAutomatorType object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/AiAutomatorType',
      $namespaces,
      $module_handler,
      'Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface',
      'Drupal\ai_automators\Attribute\AiAutomatorType'
    );
    $this->alterInfo('ai_automator_type');
    $this->setCacheBackend($cache_backend, 'ai_automator_type_plugins');
  }

}
