<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Place items should extend this class.
 */
class SchemaPlaceBase extends SchemaAddressBase {

  use SchemaPlaceTrait;

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

    $form = $this->placeForm($input_values);

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
    $keys = self::placeFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case 'address':
          $items[$key] = SchemaAddressBase::testValue();
          break;

        case 'geo':
          $items[$key] = SchemaGeoBase::testValue();
          break;

        case '@type':
          $items[$key] = 'Place';
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
  public static function processedTestValue($items) {
    foreach ($items as $key => $value) {
      switch ($key) {
        case 'address':
          $items[$key] = SchemaAddressBase::processedTestValue($items[$key]);
          break;

        case 'geo':
          $items[$key] = SchemaGeoBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
