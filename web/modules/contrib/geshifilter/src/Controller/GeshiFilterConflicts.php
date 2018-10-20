<?php

namespace Drupal\geshifilter\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Show the filters tah conflic with GeshiFilter.
 */
class GeshiFilterConflicts extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    $conflicts = self::listConflicts();
    if (count($conflicts) == 0) {
      $build = [
        '#type' => 'markup',
        '#markup' => $this->t('No conflicts found.'),
      ];
      return $build;
    }
    else {
      return [];
    }
  }

  /**
   * List all conflicts.
   *
   * @todo Make this function work, see https://www.drupal.org/node/2354511.
   *
   * @return array
   *   An array with the filter conflics found, or an empty array if there is
   *   no conflics.
   */
  public static function listConflicts() {
    return [];
  }

}
