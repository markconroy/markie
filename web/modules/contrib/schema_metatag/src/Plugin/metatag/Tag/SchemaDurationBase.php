<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Provides a plugin for the 'schema_duration_base' meta tag.
 */
class SchemaDurationBase extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    if (!empty($element)) {
      $input_value = $element['#attributes']['content'];
      $element['#attributes']['content'] = self::outputValue($input_value);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function outputValue($input_value) {
    $is_integer = ctype_digit($input_value) || is_int($input_value);
    if (!empty($element) && $is_integer && $input_value > 0) {
      return 'PT' . $input_value . 'S';
    }
    return $input_value;
  }

}
