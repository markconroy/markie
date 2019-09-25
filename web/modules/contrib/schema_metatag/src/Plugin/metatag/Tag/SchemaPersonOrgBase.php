<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Person/Org items should extend this class.
 */
class SchemaPersonOrgBase extends SchemaNameBase {

  use SchemaPersonOrgTrait;

  /**
   * The top level keys on this form.
   */
  public function formKeys() {
    return ['pivot'] + self::personOrgFormKeys();
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

    $form = $this->personOrgForm($input_values);

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
    $keys = self::personOrgFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case 'pivot':
          break;

        case 'logo':
          $items[$key] = SchemaImageBase::testValue();
          break;

        case '@type':
          $items[$key] = 'Organization';
          break;

        case 'url':
        case 'sameAs':
          $items[$key] = parent::testDefaultValue(3, ',');
          break;

        default:
          $items[$key] = parent::testDefaultValue(2, ' ');
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
        case 'url':
        case 'sameAs':
          $items[$key] = static::processTestExplodeValue($items[$key]);
          break;

        case 'logo':
          $items[$key] = SchemaImageBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
