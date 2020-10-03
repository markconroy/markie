<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Geo trait.
 */
trait SchemaGeoTrait {

  use SchemaPivotTrait;

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
  public function geoForm($input_values) {

    $input_values += $this->schemaMetatagManager()->defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@type]"]';
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
        'GeoCoordinates' => $this->t('GeoCoordinates'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['latitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('latitude'),
      '#default_value' => !empty($value['latitude']) ? $value['latitude'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The latitude of a location. For example 37.42242 (WGS 84)."),
      '#states' => $visibility,
    ];

    $form['longitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('longitude'),
      '#default_value' => !empty($value['longitude']) ? $value['longitude'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The longitude of a location. For example -122.08585 (WGS 84)."),
      '#states' => $visibility,
    ];

    return $form;
  }

}
