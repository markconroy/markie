<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class BaseUrlGlobalVariable.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "base_url",
 * );
 */
class BaseUrl extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the Base URL - for example, if you need to construct a link
    // to share on social media.
    global $base_url;
    return $base_url;
  }

}
