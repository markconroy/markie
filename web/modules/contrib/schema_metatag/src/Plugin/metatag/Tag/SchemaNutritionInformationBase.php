<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org NutritionInformation items should extend this class.
 */
class SchemaNutritionInformationBase extends SchemaNameBase {

  use SchemaNutritionInformationTrait;

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

    $form = $this->nutritionInformationForm($input_values);

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
    $keys = self::nutritionInformationFormKeys();
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'NutritionInformation';
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

}
