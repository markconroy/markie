<?php

namespace Drupal\mgv\Plugin;

/**
 * Interface GlobalVariableInterface.
 *
 * @package Drupal\mgv\Plugin
 */
interface GlobalVariableInterface {

  /**
   * Method that implement generating value of global variable.
   *
   * @return mixed
   *   Return value of declared variable.
   */
  public function getValue();

}
