<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org NutritionInformation trait.
 */
trait SchemaNutritionInformationTrait {

  use SchemaPivotTrait {
  }

  /**
   * Form keys.
   */
  public static function nutritionInformationFormKeys() {
    return [
      '@type',
      'servingSize',
      'calories',
      'carbohydrateContent',
      'cholesterolContent',
      'fiberContent',
      'proteinContent',
      'sodiumContent',
      'sugarContent',
      'fatContent',
      'saturatedFatContent',
      'unsaturatedFatContent',
      'transFatContent',
    ];
  }

  /**
   * The form element.
   */
  public function nutritionInformationForm($input_values) {

    $input_values += SchemaMetatagManager::defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $visibility_selector = $input_values['visibility_selector'];
    $selector = ':input[name="' . $visibility_selector . '[@type]"]';
    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $selector2 = SchemaMetatagManager::altSelector($selector);
    $visibility2 = ['invisible' => [$selector2 => ['value' => '']]];
    $visibility['invisible'] = [$visibility['invisible'], $visibility2['invisible']];

    $form['#type'] = 'fieldset';
    $form['#title'] = $input_values['title'];
    $form['#description'] = $input_values['description'];
    $form['#tree'] = TRUE;

    // Add a pivot option to the form.
    $form['pivot'] = $this->pivotForm($value);
    $form['pivot']['#states'] = $visibility;

    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'NutritionInformation' => $this->t('NutritionInformation'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['servingSize'] = [
      '#type' => 'textfield',
      '#title' => $this->t('servingSize'),
      '#default_value' => !empty($value['servingSize']) ? $value['servingSize'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The serving size, in terms of the number of volume or mass."),
    ];

    $form['calories'] = [
      '#type' => 'textfield',
      '#title' => $this->t('calories'),
      '#default_value' => !empty($value['calories']) ? $value['calories'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of calories."),
    ];

    $form['carbohydrateContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('carbohydrateContent'),
      '#default_value' => !empty($value['carbohydrateContent']) ? $value['carbohydrateContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of carbohydrates."),
    ];

    $form['cholesterolContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('cholesterolContent'),
      '#default_value' => !empty($value['cholesterolContent']) ? $value['cholesterolContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of milligrams of cholesterol."),
    ];

    $form['fiberContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('fiberContent'),
      '#default_value' => !empty($value['fiberContent']) ? $value['fiberContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of fiber."),
    ];

    $form['proteinContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('proteinContent'),
      '#default_value' => !empty($value['proteinContent']) ? $value['proteinContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of protein."),
    ];

    $form['sodiumContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sodiumContent'),
      '#default_value' => !empty($value['sodiumContent']) ? $value['sodiumContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of milligrams of sodium."),
    ];

    $form['sugarContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sugarContent'),
      '#default_value' => !empty($value['sugarContent']) ? $value['sugarContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of sugar."),
    ];

    $form['fatContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('fatContent'),
      '#default_value' => !empty($value['fatContent']) ? $value['fatContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of fat."),
    ];

    $form['saturatedFatContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('saturatedFatContent'),
      '#default_value' => !empty($value['saturatedFatContent']) ? $value['saturatedFatContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of saturated fat."),
    ];

    $form['unsaturatedFatContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('unsaturatedFatContent'),
      '#default_value' => !empty($value['unsaturatedFatContent']) ? $value['unsaturatedFatContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of unsaturated fat."),
    ];

    $form['transFatContent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('transFatContent'),
      '#default_value' => !empty($value['transFatContent']) ? $value['transFatContent'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The number of grams of trans fat."),
    ];

    $keys = static::nutritionInformationFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
