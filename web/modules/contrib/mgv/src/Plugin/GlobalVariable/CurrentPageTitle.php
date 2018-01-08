<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class CurrentPageTitle.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_page_title",
 * );
 */
class CurrentPageTitle extends RawCurrentPageTitle {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $value = parent::getValue();
    if (!empty($value)) {
      if (is_array($value)) {
        $value = urlencode($value['#markup']);
      }
      elseif (is_object($value)) {
        $value = render($value);
      }
    }
    return $value;
  }

}
