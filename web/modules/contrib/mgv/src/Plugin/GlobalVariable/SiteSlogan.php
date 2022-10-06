<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SiteSlogan.
 *
 * Site Information Page Global Variables.
 * Print the Site Slogan.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "site_slogan",
 * );
 */
class SiteSlogan extends SystemSiteBase {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->config->get('slogan');
  }

}
