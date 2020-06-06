<?php

namespace Drupal\upgrade_status_test_error\Controller;

use Drupal\Core\Controller\ControllerBase;

class UpgradeStatusTestErrorController extends ControllerBase {

  public function content() {
    menu_cache_clear_all();

    return [
      '#type' => 'markup',
      '#markup' => $this->t('I am deprecated.'),
    ];
  }

}
