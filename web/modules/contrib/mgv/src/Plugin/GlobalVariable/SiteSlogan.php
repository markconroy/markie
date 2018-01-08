<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SiteSlogan.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "site_slogan",
 * );
 */
class SiteSlogan extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Site Information Page Global Variables.
    //
    // Print the Site Slogan.
    return \Drupal::config('system.site')->get('slogan');
  }

}
