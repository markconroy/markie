<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Answer trait.
 */
trait SchemaAnswerTrait {

  use SchemaPersonOrgTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
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
  public function answerForm($input_values) {

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
        'Answer' => $this->t('Answer'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('text'),
      '#default_value' => !empty($value['text']) ? $value['text'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('REQUIRED BY GOOGLE. The full text of the answer.'),
      '#states' => $visibility,

    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('STRONGLY RECOMMENDED BY GOOGLE. A URL that links directly to this answer.'),
      '#states' => $visibility,
    ];

    $form['upvoteCount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('upvoteCount'),
      '#default_value' => !empty($value['upvoteCount']) ? $value['upvoteCount'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("RECOMMENDED BY GOOGLE. The total number of votes that this answer has received."),
      '#states' => $visibility,
    ];

    $form['dateCreated'] = [
      '#type' => 'textfield',
      '#title' => $this->t('dateCreated'),
      '#default_value' => !empty($value['dateCreated']) ? $value['dateCreated'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('RECOMMENDED BY GOOGLE. The date at which the answer was added to the page, in ISO-8601 format.'),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('Author'),
      'description' => 'RECOMMENDED BY GOOGLE. The author of the answer.',
      'value' => !empty($value['author']) ? $value['author'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[author]',
    ];

    $form['author'] = $this->personOrgForm($input_values);
    $form['author']['#states'] = $visibility;

    return $form;
  }

}
