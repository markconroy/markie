<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin to extend for the 'Review' meta tag.
 */
class SchemaReviewBase extends SchemaNameBase {

  use SchemaReviewTrait;

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
    $form = $this->reviewForm($input_values);

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
    $keys = static::reviewFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'Review';
          break;

        case 'author':
          $items[$key] = [
            '@type' => 'Person',
            'name' => parent::testDefaultValue(2, ' '),
          ];
          break;

        case 'reviewRating':
          $items[$key] = [
            '@type' => 'Rating',
            'ratingValue' => parent::testDefaultValue(2, ' '),
          ];
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
