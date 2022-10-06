<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentPath.
 *
 * Site Information Page Global Variables.
 * Print the Site Name. For example, you might want to have a
 * Copyright "My Site Name" message in the footer.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "site_name",
 * );
 */
class SiteName extends SystemSiteBase {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->config->get('name');
  }

}
