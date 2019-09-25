<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin to extend for the 'Rating' meta tag.
 */
class SchemaRatingBase extends SchemaNameBase {

  use SchemaRatingTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $value = SchemaMetatagManager::unserialize($this->value());
    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
    ];

    $form = parent::form($element);
    $form = $this->ratingForm($input_values);

    if (empty($this->multiple())) {
      unset($form['pivot']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = static::ratingFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'Rating';
          break;

        default:
          $items[$key] = parent::testDefaultValue(2, ' ');
          break;

      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function outputValue($input_value) {
    return $input_value;
  }

}
