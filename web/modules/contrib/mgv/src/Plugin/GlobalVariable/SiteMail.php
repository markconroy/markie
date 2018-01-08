<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SiteMail.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "site_mail",
 * );
 */
class SiteMail extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Site Information Page Global Variables.
    //
    // Print the Site Email.
    return \Drupal::config('system.site')->get('mail');
  }

}
