<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org OpeningHoursSpecification items should extend this class.
 */
class SchemaOpeningHoursSpecificationBase extends SchemaNameBase {

  use SchemaOpeningHoursSpecificationTrait;

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

    $form = $this->openingHoursSpecificationForm($input_values);

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
    $keys = self::openingHoursSpecificationFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'OpeningHoursSpecification';
          break;

        case 'dayOfWeek':
        case 'opens':
        case 'closes';
          $items[$key] = parent::testDefaultValue(3, ',');
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
        case 'dayOfWeek':
        case 'opens':
        case 'closes':
          $items[$key] = static::processTestExplodeValue($items[$key]);
          break;
      }
    }
    return $items;
  }

}
