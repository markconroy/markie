<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * All Schema.org itemListElement tags should extend this class.
 */
class SchemaItemListElementBase extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#description'] = $this->t('To create a list, provide a token for a multiple value field, or a comma-separated list of values.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function outputValue($input_value) {
    $items = [];
    $values = static::getItems($input_value);
    if (!empty($values) && is_array($values)) {
      foreach ($values as $key => $value) {
        if (is_array($value)) {
          // Maps to Google all-in-one page view.
          if (array_key_exists('@type', $value)) {
            $items[] = [
              '@type' => 'ListItem',
              'position' => $key,
              'item' => $value,
            ];
          }
          // Maps to Google summary list view.
          elseif (array_key_exists('url', $value)) {
            $items[] = [
              '@type' => 'ListItem',
              'position' => $key,
              'url' => $value['url'],
            ];
          }
          // Maps to breadcrumb list.
          elseif (array_key_exists('name', $value) && array_key_exists('item', $value)) {
            $items[] = [
              '@type' => 'ListItem',
              'position' => $key,
              'name' => $value['name'],
              'item' => $value['item'],
            ];
          }
        }
        // Alternative simple list.
        else {
          $items[] = $value;
        }
      }
    }
    return $items;
  }

  /**
   * Process the input value into an array of items.
   *
   * Each type of ItemList can extend this to process the input value into a
   * list of items. The default behavior will be a simple array from a
   * comma-separated list.
   */
  public static function getItems($input_value) {
    if (!is_array($input_value)) {
      $input_value = SchemaMetatagManager::explode($input_value);
    }
    return $input_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return static::testDefaultValue(3, ',');
  }

}
