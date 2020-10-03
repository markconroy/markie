<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Brand items should extend this class.
 */
class SchemaBrandBase extends SchemaNameBase {

  use SchemaBrandTrait;

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

    $form = $this->brandForm($input_values);

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
      'description',
      'url',
      'sameAs',
      'logo',
    ];
    foreach ($keys as $key) {
      switch ($key) {
        case 'logo':
          $items[$key] = SchemaImageBase::testValue();
          break;

        case '@type':
          $items[$key] = 'Brand';
          break;

        default:
          $items[$key] = parent::testDefaultValue(2, ' ');
          break;

      }
    }
    return $items;
  }

}
