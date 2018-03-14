<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentLangname.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_langname",
 * );
 */
class CurrentLangname extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the current langname, e.g. 'english' or 'french'.
    return \Drupal::languageManager()->getCurrentLanguage()->getName();
  }

}
