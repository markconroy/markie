<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org OpeningHoursSpecification trait.
 */
trait SchemaOpeningHoursSpecificationTrait {

  use SchemaPivotTrait {
  }

  /**
   * Return the SchemaMetatagManager.
   *
   * @return \Drupal\schema_metatag\SchemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  abstract protected function schemaMetatagManager();

  /**
   * The form element.
   */
  public function openingHoursSpecificationForm($input_values) {

    $input_values += $this->schemaMetatagManager()->defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $visibility_selector = $input_values['visibility_selector'];
    $selector = ':input[name="' . $visibility_selector . '[@type]"]';
    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $selector2 = $this->schemaMetatagManager()->altSelector($selector);
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
        'OpeningHoursSpecification' => $this->t('OpeningHoursSpecification'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['dayOfWeek'] = [
      '#type' => 'textfield',
      '#title' => $this->t('dayOfWeek'),
      '#default_value' => !empty($value['dayOfWeek']) ? $value['dayOfWeek'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Comma-separated list of the names of the days of the week."),
      '#states' => $visibility,
    ];

    $form['opens'] = [
      '#type' => 'textfield',
      '#title' => $this->t('opens'),
      '#default_value' => !empty($value['opens']) ? $value['opens'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Matching comma-separated list of the time the business location opens each day, in hh:mm:ss format."),
      '#states' => $visibility,
    ];

    $form['closes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('closes'),
      '#default_value' => !empty($value['closes']) ? $value['closes'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Matching comma-separated list of the time the business location closes each day, in hh:mm:ss format."),
      '#states' => $visibility,
    ];

    $form['validFrom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('validFrom'),
      '#default_value' => !empty($value['validFrom']) ? $value['validFrom'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The date of a seasonal business closure, in YYYY-MM-DD format."),
      '#states' => $visibility,
    ];

    $form['validThrough'] = [
      '#type' => 'textfield',
      '#title' => $this->t('validThrough'),
      '#default_value' => !empty($value['validThrough']) ? $value['validThrough'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The date of a seasonal business closure, in YYYY-MM-DD format."),
      '#states' => $visibility,
    ];

    return $form;
  }

}
