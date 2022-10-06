<?php

namespace Drupal\mgv\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class GlobalVariable.
 *
 * Base class used for global variable definition.
 *
 * @package Drupal\mgv\Plugin
 */
abstract class GlobalVariable extends PluginBase implements GlobalVariableInterface {

  use StringTranslationTrait;

  /**
   * List of global variables which is used by variable implementation.
   *
   * @var array|mixed
   *   List of dependencies.
   */
  protected $dependency;

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\mgv\MgvPluginManager::createInstance()
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dependency = !empty($configuration['variableDependencies']) ?
      $configuration['variableDependencies'] :
      [];
  }

  /**
   * Dependency getter.
   *
   * @param string $id
   *   Name of dependency.
   *
   * @return array|mixed
   *   Value of dependent global variable.
   */
  public function getDependency(string $id) {
    return !empty($this->dependency[$id]) ? $this->dependency[$id] : '';
  }

}
