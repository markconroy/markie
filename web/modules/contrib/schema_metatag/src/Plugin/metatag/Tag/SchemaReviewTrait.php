<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Review trait.
 */
trait SchemaReviewTrait {

  use SchemaRatingTrait, SchemaPersonOrgTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaRatingTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
  }

  /**
   * Form keys.
   */
  public static function reviewFormKeys() {
    return [
      '@type',
      'reviewBody',
      'datePublished',
      'author',
      'reviewRating',
    ];
  }

  /**
   * The form element.
   */
  public function reviewForm($input_values) {

    $input_values += SchemaMetatagManager::defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $visibility_selector = $input_values['visibility_selector'];
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
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Review' => $this->t('Review'),
        'UserReview' => $this->t('- UserReview'),
        'CriticReview' => $this->t('- CriticReview'),
        'EmployerReview' => $this->t('- EmployerReview'),
        'ClaimReview' => $this->t('ClaimReview'),
      ],
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#weight' => -10,
    ];

    $form['reviewBody'] = [
      '#type' => 'textfield',
      '#title' => $this->t('reviewBody'),
      '#default_value' => !empty($value['reviewBody']) ? $value['reviewBody'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The actual body of the review.'),
    ];

    $form['datePublished'] = [
      '#type' => 'textfield',
      '#title' => $this->t('datePublished'),
      '#default_value' => !empty($value['datePublished']) ? $value['datePublished'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('To format the date properly, use a token like [node:created:html_datetime].'),
    ];

    $input_values = [
      'title' => $this->t('author'),
      'description' => 'The author of this review.',
      'value' => !empty($value['author']) ? $value['author'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->getPluginId() . '[author][@type]',
    ];

    $input_values = [
      'title' => $this->t('author'),
      'description' => 'The author of this review.',
      'value' => !empty($value['author']) ? $value['author'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[author]',
    ];
    $form['author'] = $this->personOrgForm($input_values);

    $input_values = [
      'title' => $this->t('reviewRating'),
      'description' => 'The rating of this review.',
      'value' => !empty($value['reviewRating']) ? $value['reviewRating'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[reviewRating]',
    ];
    $form['reviewRating'] = $this->ratingForm($input_values);

    $keys = static::reviewFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
