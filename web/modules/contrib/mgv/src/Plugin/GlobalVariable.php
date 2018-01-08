<?php

namespace Drupal\mgv\Plugin;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class GlobalVariable.
 *
 * @package Drupal\mgv\Plugin
 */
abstract class GlobalVariable implements GlobalVariableInterface {

  use StringTranslationTrait;

  private $configurations;

  private $dependency;

  /**
   * GlobalVariable constructor.
   *
   * @param array $configurations
   *   Configuration that collected from createInstance() method.
   *
   * @see \Drupal\mgv\MgvPluginManager::createInstance()
   */
  public function __construct(array $configurations) {
    $this->configurations = $configurations;
    $this->dependency = empty($this->configurations['variableDependencies']) ?
      [] :
      $this->configurations['variableDependencies'];
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
  public function getDependency($id) {
    return empty($this->dependency[$id]) ? NULL : $this->dependency[$id];
  }

}
