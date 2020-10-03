<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org place trait.
 */
trait SchemaPlaceTrait {

  use SchemaAddressTrait, SchemaGeoTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaAddressTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaGeoTrait;
  }

  /**
   * Return the SchemaMetatagManager.
   *
   * @return \Drupal\schema_metatag\SchemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  abstract protected function schemaMetatagManager();

  /**
   * {@inheritdoc}
   */
  public function placeForm($input_values) {

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
        'Place' => $this->t('Place'),
        'VirtualLocation' => $this->t('VirtualLocation'),
        'AdministrativeArea' => $this->t('AdministrativeArea'),
        'Country' => $this->t('- Country'),
        'State' => $this->t('- State'),
        'City' => $this->t('- City'),
        'SchoolDistrict' => $this->t('- SchoolDistrict'),
        'CivicStructure' => $this->t('CivicStructure'),
        'LocalBusiness' => $this->t('LocalBusiness'),
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
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[geo]',
    ];

    $form['geo'] = $this->geoForm($input_values);
    $form['geo']['#states'] = $visibility;

    return $form;
  }

}
