<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for the 'hasPart' meta tag.
 *
 * Currently applies only to isAccessibleForFree.
 *
 * @see https://developers.google.com/search/docs/data-types/paywalled-content
 */
class SchemaHasPartBase extends SchemaNameBase {

  use SchemaHasPartTrait;

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

    $form = $this->hasPartForm($input_values);

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
    $keys = self::hasPartFormKeys();
    foreach ($keys as $key) {
      switch ($key) {

        case '@type':
          $items[$key] = 'WebPageElement';
          break;

        case 'potentialAction':
          $items[$key] = SchemaActionBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function processedTestValue($items) {
    foreach ($items as $key => $value) {
      switch ($key) {
        case 'potentialAction':
          $items[$key] = SchemaActionBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
