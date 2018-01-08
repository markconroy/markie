<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentPath.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_path",
 * );
 */
class CurrentPath extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the current path. This could be useful if you want to do a redirect
    // after a form is submitted, e.g. ?destination={{ current_path }}.
    return \Drupal::service('path.current')->getPath();
  }

}
