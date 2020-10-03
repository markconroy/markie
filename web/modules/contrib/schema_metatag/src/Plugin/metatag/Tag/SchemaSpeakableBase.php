<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Speakable items should extend this class.
 */
class SchemaSpeakableBase extends SchemaNameBase {

  use SchemaSpeakableTrait;

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

    $form = $this->speakableForm($input_values);

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
      'xpath',
      'cssSelector',
    ];
    foreach ($keys as $key) {
      switch ($key) {
        case 'pivot':
          break;

        case '@type':
          $items[$key] = 'SpeakableSpecification';
          break;

        case 'xpath':
          $items[$key] = '/html/head/title,/html/head/meta[@name=\'description\']/@content';
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
        case 'xpath':
        case 'cssSelector':
          $items[$key] = static::processTestExplodeValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
