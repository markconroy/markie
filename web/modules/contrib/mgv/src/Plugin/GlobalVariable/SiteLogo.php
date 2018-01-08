<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SiteLogo.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "logo",
 * );
 */
class SiteLogo extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the Site logo's URL. - we are only printing the URL so you can add
    // custom alt (and other) attributes to the image if you wish.
    $theme_name = \Drupal::theme()->getActiveTheme()->getName();
    return theme_get_setting('logo.url', $theme_name);
  }

}
