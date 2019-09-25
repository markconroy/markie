<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org tags using an id reference items should extend this class.
 */
class SchemaIdReferenceBase extends SchemaNameBase {

  use SchemaIdReferenceTrait;

  /**
   * The top level keys on this form.
   */
  public function formKeys() {
    return ['pivot'] + self::idFormKeys();
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

    $form = $this->idForm($input_values);

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
    $keys = self::idFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case 'pivot':
          break;

        case '@id':
          $items[$key] = parent::testDefaultValue(3, ',');
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
        case '@id':
          $items[$key] = static::processTestExplodeValue($items[$key]);
          break;
      }
    }
    return $items;
  }

}
