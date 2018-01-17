<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;
use \Drupal\schema_metatag\Plugin\metatag\Tag\SchemaAddressBase;

/**
 * Schema.org Place items should extend this class.
 */
abstract class SchemaPlaceBase extends SchemaAddressBase {

  /**
   * Traits provide re-usable form elements.
   */
  use SchemaAddressTrait;
  use SchemaGeoTrait;

  /**
   * The top level keys on this form.
   */
  function form_keys() {
    return [
      '@type',
      'name',
      'url',
      'address',
      'geo',
    ];
  }

  /**
   * Generate a form element for this meta tag.
   *
   * We need multiple values, so create a tree of values and
   * stored the serialized value as a string.
   */

  public function form(array $element = []) {

    $value = SchemaMetatagManager::unserialize($this->value());

    // Get the id for the nested @type element.
    $selector = $this->visibilitySelector() . '[@type]';
    $visibility = ['visible' => [
      ":input[name='$selector']" => ['value' => 'Place']]
    ];

    $form['#type'] = 'fieldset';
    $form['#description'] = $this->description();
    $form['#open'] = !empty($value['name']);
    $form['#tree'] = TRUE;
    $form['#title'] = $this->label();
    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Place' => $this->t('Place'),
      ],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t('The name of the place'),
      '#states' => $visibility,
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t('The url of the place.'),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('Address'),
      'description' => 'The address of the place.',
      'value' => !empty($value['address']) ? $value['address'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector() . '[address][@type]',
    ];

    $form['address'] = $this->postal_address_form($input_values);
    $form['address']['#states'] = $visibility;

    $input_values = [
      'title' => $this->t('GeoCoordinates'),
      'description' => 'The geo coordinates of the place.',
      'value' => !empty($value['geo']) ? $value['geo'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector() . '[geo][@type]',
    ];

    $form['geo'] = $this->geo_form($input_values);
    $form['geo']['#states'] = $visibility;

    return $form;
  }

}
