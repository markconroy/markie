<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentPath.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "site_name",
 * );
 */
class SiteName extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Site Information Page Global Variables.
    //
    // Print the Site Name. For example, you might want to have a
    // Copyright "My Site Name" message in the footer.
    return \Drupal::config('system.site')->get('name');
  }

}
