<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org PostalAddress items should extend this class.
 */
class SchemaAddressBase extends SchemaNameBase {

  use SchemaAddressTrait;

  /**
   * The top level keys on this form.
   */
  public static function formKeys() {
    return ['pivot'] + self::postalAddressFormKeys();
  }

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

    $form = $this->postalAddressForm($input_values);

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
    $keys = self::postalAddressFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'PostalAddress';
          break;

        default:
          $items[$key] = parent::testDefaultValue(2, ' ');
          break;

      }
    }
    return $items;
  }

}
