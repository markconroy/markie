<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Thing items should extend this class.
 */
class SchemaThingBase extends SchemaNameBase {

  use SchemaThingTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {

    $value = $this->schemaMetatagManager()->unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
    ];

    $form = $this->thingForm($input_values);

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
    $keys = [
      '@type',
      '@id',
      'name',
      'url',
    ];
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'Thing';
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

}
