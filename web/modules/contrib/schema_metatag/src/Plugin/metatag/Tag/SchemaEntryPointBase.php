<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org EntryPoint items should extend this class.
 */
class SchemaEntryPointBase extends SchemaNameBase {

  use SchemaEntryPointTrait;

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

    $form = $this->entryPointForm($input_values);

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
    $keys = self::entryPointFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'EntryPoint';
          break;

        case 'urlTemplate':
        case 'actionPlatform':
        case 'inLanguage';
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
        case 'urlTemplate':
        case 'actionPlatform':
        case 'inLanguage';
          $items[$key] = static::processTestExplodeValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
