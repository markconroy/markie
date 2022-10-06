<?php

namespace Drupal\mgv;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\mgv\Plugin\GlobalVariableInterface;

/**
 * Class MgvPluginManager.
 *
 * @package Drupal\mgv\Annotation
 *
 * @Annotation
 */
class MgvPluginManager extends DefaultPluginManager implements MgvPluginManagerInterface {

  /**
   * Variables list.
   *
   * @var array
   */
  private $variables = [];

  /**
   * Constructs GlobalVariablePluginManager.
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
      'Plugin' . DIRECTORY_SEPARATOR . 'GlobalVariable',
      $namespaces,
      $module_handler,
      'Drupal\mgv\Plugin\GlobalVariableInterface',
      'Drupal\mgv\Annotation\Mgv'
    );
    $this->setCacheBackend($cache_backend, 'mgv');
    $this->factory = new ContainerFactory($this->getDiscovery(), 'Drupal\mgv\Plugin\GlobalVariableInterface');
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables() {
    if (empty($this->variables)) {
      $this->variables = [];
      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        /** @var \Drupal\mgv\Plugin\GlobalVariableInterface $variable */
        $variable = $this->createInstance($plugin_id, $definition);
        $this->variables = NestedArray::mergeDeep(
          $this->variables,
          $this->getNamespacedValue($plugin_id, $variable),
        );
      }
    }

    return $this->variables;
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespacedValue($plugin_id, GlobalVariableInterface $variable) {
    $value = $variable->getValue();
    $namespaces = explode('\\', $plugin_id);
    foreach (array_reverse($namespaces) as $name) {
      $value = [$name => $value];
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if (!empty($configuration['variableDependencies'])) {
      foreach ($configuration['variableDependencies'] as $key => $plugin) {
        /** @var \Drupal\mgv\Plugin\GlobalVariableInterface $instance */
        $instance = $this->createInstance($plugin);
        unset($configuration['variableDependencies'][$key]);
        $configuration['variableDependencies'][$plugin] = $instance->getValue();
      }
    }
    return parent::createInstance($plugin_id, $configuration);
  }

}
