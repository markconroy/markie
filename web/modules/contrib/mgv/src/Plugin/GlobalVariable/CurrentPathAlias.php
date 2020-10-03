<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentPathAlias.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_path_alias",
 * );
 */
class CurrentPathAlias extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the current path alias. This could be useful if you want to ensure
    // the alias rather than the path is used.
    $current_path = \Drupal::service('path.current')->getPath();
    return \Drupal::service('path_alias.manager')->getAliasByPath($current_path);
  }

}
