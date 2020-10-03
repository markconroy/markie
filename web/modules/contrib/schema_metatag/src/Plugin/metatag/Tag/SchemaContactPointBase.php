<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Contact Point items should extend this class.
 */
class SchemaContactPointBase extends SchemaNameBase {

  use SchemaContactPointTrait;

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

    $form = $this->contactPointForm($input_values);

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
      'areaServed',
      'availableLanguage',
      'contactType',
      'contactOption',
      'email',
      'faxnumber',
      'productSupported',
      'telephone',
      'url',
    ];
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'ContactPoint';
          break;

        case 'areaServed':
          $items[$key] = SchemaPlaceBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

}
