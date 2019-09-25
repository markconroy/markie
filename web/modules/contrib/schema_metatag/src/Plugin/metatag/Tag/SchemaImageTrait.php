<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Image trait.
 */
trait SchemaImageTrait {

  use SchemaPivotTrait;

  /**
   * Form keys.
   */
  public static function imageFormKeys() {
    return [
      '@type',
      'representativeOfPage',
      'url',
      'width',
      'height',
    ];
  }

  /**
   * The form element.
   */
  public function imageForm($input_values) {

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

    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'ImageObject' => $this->t('ImageObject'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['representativeOfPage'] = [
      '#type' => 'select',
      '#title' => $this->t('representative Of Page'),
      '#empty_option' => t('False'),
      '#empty_value' => '',
      '#options' => ['True' => 'True'],
      '#default_value' => !empty($value['representativeOfPage']) ? $value['representativeOfPage'] : '',
      '#required' => $input_values['#required'],
      '#description' => $this->t('Whether this image is representative of the content of the page.'),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Absolute URL of the image, i.e. [node:field_name:image_preset_name:url].'),
    ];

    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('width'),
      '#default_value' => !empty($value['width']) ? $value['width'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
    ];

    $form['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('height'),
      '#default_value' => !empty($value['height']) ? $value['height'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
    ];

    $keys = static::imageFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
