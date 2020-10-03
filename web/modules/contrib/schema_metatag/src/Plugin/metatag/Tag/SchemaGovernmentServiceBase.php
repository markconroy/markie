<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org GovernmentService items should extend this class.
 */
class SchemaGovernmentServiceBase extends SchemaNameBase {

  use SchemaGovernmentServiceTrait;

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

    $form = $this->governmentServiceForm($input_values);

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
      'name',
      'url',
      'serviceType',
      'provider',
      'audience',
    ];
    foreach ($keys as $key) {
      switch ($key) {
        case 'provider':
          $items[$key] = SchemaPersonOrgBase::testValue();
          break;

        case '@type':
          $items[$key] = 'GovernmentService';
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
        case 'provider':
          $items[$key] = SchemaPersonOrgBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
