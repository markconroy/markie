<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Thing trait.
 */
trait SchemaThingTrait {

  use SchemaPivotTrait;

  /**
   * Form keys.
   */
  public static function thingFormKeys() {
    return [
      '@type',
      '@id',
      'name',
      'url',
    ];
  }

  /**
   * The form element.
   */
  public function thingForm($input_values) {

    $input_values += SchemaMetatagManager::defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@type]"]';
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

    $options = static::types();
    $options = array_combine($options, $options);
    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $options,
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Globally unique @id of the thing, usually a url, used to to link other properties to this object."),
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Name of the thing."),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Absolute URL of the canonical Web page for the thing."),
    ];

    $keys = static::thingFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

  /**
   * Thing object types.
   */
  public static function types() {
    return [
      'Thing',
      'CreativeWork',
      'Event',
      'Intangible',
      'Organization',
      'Person',
      'Place',
      'Product',
    ];
  }

}
