<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Question trait.
 */
trait SchemaQuestionTrait {

  use SchemaAnswerTrait, SchemaPersonOrgTrait, SchemaPivotTrait {
    SchemaPersonOrgTrait::personOrgForm insteadof SchemaAnswerTrait;
    SchemaPersonOrgTrait::personOrgFormKeys insteadof SchemaAnswerTrait;
    SchemaPersonOrgTrait::imageForm insteadof SchemaAnswerTrait;
    SchemaPersonOrgTrait::imageFormKeys insteadof SchemaAnswerTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaAnswerTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
  }

  /**
   * Form keys.
   */
  public static function questionFormKeys() {
    return [
      '@type',
      'name',
      'text',
      'upvoteCount',
      'answerCount',
      'acceptedAnswer',
      'suggestedAnswer',
      'dateCreated',
      'author',
    ];
  }

  /**
   * The form element.
   */
  public function questionForm($input_values) {

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
        'Question' => $this->t('Question'),
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
      '#description' => $this->t('REQUIRED BY GOOGLE. The full text of the short form of the question. For example, "How many teaspoons in a cup?".'),
    ];

    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('text'),
      '#default_value' => !empty($value['text']) ? $value['text'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('RECOMMENDED BY GOOGLE. The full text of the long form of the question.'),
    ];

    $form['upvoteCount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('upvoteCount'),
      '#default_value' => !empty($value['upvoteCount']) ? $value['upvoteCount'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("RECOMMENDED BY GOOGLE. The total number of votes that this question has received."),
    ];

    $form['answerCount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('answerCount'),
      '#default_value' => !empty($value['answerCount']) ? $value['answerCount'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("REQUIRED BY GOOGLE. The total number of answers to the question. This may also be 0 for questions with no answers."),
    ];

    $form['dateCreated'] = [
      '#type' => 'textfield',
      '#title' => $this->t('dateCreated'),
      '#default_value' => !empty($value['dateCreated']) ? $value['dateCreated'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('RECOMMENDED BY GOOGLE. The date at which the question was added to the page, in ISO-8601 format.'),
    ];

    // Add nested objects.
    $input_values = [
      'title' => $this->t('acceptedAnswer'),
      'description' => 'A top answer to the question. There can be zero or more of these per question. Either acceptedAnswer OR suggestedAnswer is REQUIRED BY GOOGLE.',
      'value' => !empty($value['acceptedAnswer']) ? $value['acceptedAnswer'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $visibility_selector . '[acceptedAnswer]',
    ];
    $form['acceptedAnswer'] = $this->answerForm($input_values);

    $input_values = [
      'title' => $this->t('suggestedAnswer'),
      'description' => 'One possible answer, but not accepted as a top answer (acceptedAnswer). There can be zero or more of these per Question. Either acceptedAnswer OR suggestedAnswer is REQUIRED BY GOOGLE.',
      'value' => !empty($value['suggestedAnswer']) ? $value['suggestedAnswer'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $visibility_selector . '[suggestedAnswer]',
    ];
    $form['suggestedAnswer'] = $this->answerForm($input_values);

    $input_values = [
      'title' => $this->t('Author'),
      'description' => 'RECOMMENDED BY GOOGLE. The author of the question.',
      'value' => !empty($value['author']) ? $value['author'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $visibility_selector . '[author]',
    ];
    $form['author'] = $this->personOrgForm($input_values);

    // Add visibility settings to hide fields when the type is empty.
    $keys = static::questionFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
