<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SiteMail.
 *
 * Site Information Page Global Variables.
 * Print the Site Email.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "site_mail",
 * );
 */
class SiteMail extends SystemSiteBase {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->config->get('mail');
  }

}
