<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentLangcode.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_langcode",
 * );
 */
class CurrentLangcode extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the current langcode. This could be useful if you want to do a
    // "Back to Search" type feature, but need to ensure you keep the current
    // selected language e.g. "/fr/node/123" and "/ga/node/123".
    return \Drupal::languageManager()->getCurrentLanguage()->getId();
  }

}
