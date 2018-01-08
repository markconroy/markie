<?php

namespace Drupal\mgv\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Class Mgv.
 *
 * @package Drupal\mgv\Annotation
 *
 * @Annotation
 */
class Mgv extends Plugin {

  /**
   * Variable name.
   *
   * @var string
   */
  public $id;

  /**
   * Dependencies to other variable values.
   *
   * @var array
   */
  public $variableDependencies = [];

  /**
   * Variable name.
   *
   * @return string
   *   Variable name.
   */
  public function getName() {
    return $this->id;
  }

  /**
   * Dependencies to other variable values.
   *
   * @return \array[]
   *   Dependencies to other variable values.
   */
  public function getVariableDependencies() {
    return (empty($this->variableDependencies) || !is_array($this->variableDependencies)) ?
      [] :
      $this->variableDependencies;
  }

}
