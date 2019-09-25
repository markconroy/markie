<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org MonetaryAmount should extend this class.
 */
class SchemaMonetaryAmountBase extends SchemaNameBase {

  use SchemaMonetaryAmountTrait;

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

    $form = $this->monetaryAmountForm($input_values);

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
    $keys = self::monetaryAmountFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'MonetaryAmount';
          break;

        case 'value':
          $items[$key] = [
            '@type' => 'QuantitativeValue',
            'value' => parent::testDefaultValue(1, ''),
            'minValue' => parent::testDefaultValue(1, ''),
            'maxValue' => parent::testDefaultValue(1, ''),
            'unitText' => 'HOUR',
          ];
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

}
