<?php

namespace Drupal\geshifield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'geshifield_default' formatter.
 *
 * @FieldFormatter(
 *   id = "geshifield_default",
 *   label = @Translation("GeshiField default"),
 *   field_types = {
 *     "geshifield"
 *   }
 * )
 */
class GeshiFieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $source = [
        '#theme' => 'geshifield_default',
        '#language' => $item->language,
        '#sourcecode' => $item->sourcecode,
      ];
      $elements[$delta] = ['#markup' => drupal_render($source)];
    }

    return $elements;
  }

}
