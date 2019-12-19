<?php

namespace Drupal\mgv;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\mgv\Plugin\GlobalVariableInterface;

/**
 * Interface MgvPluginManagerInterface.
 *
 * @package Drupal\mgv
 */
interface MgvPluginManagerInterface extends PluginManagerInterface {

  /**
   * Returns all the variables.
   *
   * @return array[mixed]
   *   Variables list.
   */
  public function getVariables();

  /**
   * Create namespaced value of given global variable id and instance.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param \Drupal\mgv\Plugin\GlobalVariableInterface $variable
   *   Plugin instance.
   *
   * @return array|mixed
   *   Generated value.
   */
  public function getNamespacedValue($plugin_id, GlobalVariableInterface $variable);

}
