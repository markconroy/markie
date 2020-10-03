<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Rating trait.
 */
trait SchemaRatingTrait {

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
  public function ratingForm($input_values) {

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
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Rating' => $this->t('Rating'),
        'AggregateRating' => $this->t('AggregateRating'),
      ],
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#weight' => -10,
    ];

    $form['ratingValue'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ratingValue'),
      '#default_value' => !empty($value['ratingValue']) ? $value['ratingValue'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The numeric rating of the item.'),
      '#states' => $visibility,
    ];

    $form['ratingCount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ratingCount'),
      '#default_value' => !empty($value['ratingCount']) ? $value['ratingCount'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The number of ratings included. Only required for AggregateRating.'),
      '#states' => $visibility,
    ];

    $form['bestRating'] = [
      '#type' => 'textfield',
      '#title' => $this->t('bestRating'),
      '#default_value' => !empty($value['bestRating']) ? $value['bestRating'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The highest rating value possible.'),
      '#states' => $visibility,
    ];

    $form['worstRating'] = [
      '#type' => 'textfield',
      '#title' => $this->t('worstRating'),
      '#default_value' => !empty($value['worstRating']) ? $value['worstRating'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The lowest rating value possible.'),
      '#states' => $visibility,
    ];

    return $form;
  }

}
