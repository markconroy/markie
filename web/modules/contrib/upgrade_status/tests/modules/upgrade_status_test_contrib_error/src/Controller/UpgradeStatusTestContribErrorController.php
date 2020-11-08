<?php

namespace Drupal\upgrade_status_test_contrib_error\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Test class which contains deprecation error.
 */
class UpgradeStatusTestContribErrorController extends ControllerBase {

  public function content() {
    drupal_set_message('I am deprecated');
  }

}
