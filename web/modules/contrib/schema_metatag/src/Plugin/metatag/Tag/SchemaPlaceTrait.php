<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org place trait.
 */
trait SchemaPlaceTrait {

  use SchemaAddressTrait, SchemaGeoTrait, SchemaCountryTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaAddressTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaGeoTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaCountryTrait;
  }

  /**
   * The top level keys on this form.
   */
  public static function placeFormKeys() {
    return [
      '@type',
      'name',
      'url',
      'address',
      'geo',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function placeForm($input_values) {

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
        'Place' => $this->t('Place'),
        'AdministrativeArea' => $this->t('AdministrativeArea'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The name of the place'),
      '#states' => $visibility,
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The url of the place.'),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('Address'),
      'description' => 'The address of the place.',
      'value' => !empty($value['address']) ? $value['address'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[address]',
    ];

    $form['address'] = $this->postalAddressForm($input_values);
    $form['address']['#states'] = $visibility;

    $input_values = [
      'title' => $this->t('GeoCoordinates'),
      'description' => 'The geo coordinates of the place.',
      'value' => !empty($value['geo']) ? $value['geo'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $visibility_selector . '[geo]',
    ];

    $form['geo'] = $this->geoForm($input_values);
    $form['geo']['#states'] = $visibility;

    $input_values = [
      'title' => $this->t('Country'),
      'description' => 'The country of the place.',
      'value' => !empty($value['country']) ? $value['country'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $visibility_selector . '[country]',
    ];

    $form['country'] = $this->countryForm($input_values);
    $form['country']['#states'] = $visibility;

    return $form;
  }

}
