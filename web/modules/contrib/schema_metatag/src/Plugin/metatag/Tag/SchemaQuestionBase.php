<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Question items should extend this class.
 */
class SchemaQuestionBase extends SchemaNameBase {

  use SchemaQuestionTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {

    $value = $this->schemaMetatagManager()->unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($value['#required']) ? $value['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
    ];

    $form = $this->questionForm($input_values);

    if (empty($this->multiple())) {
      unset($form['pivot']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = [
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
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'Question';
          break;

        case 'author':
          $items[$key] = SchemaPersonOrgBase::testValue();
          break;

        case 'acceptedAnswer':
        case 'suggestedAnswer':
          $items[$key] = SchemaAnswerBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function processedTestValue($items) {
    foreach ($items as $key => $value) {
      switch ($key) {
        case 'author':
          $items[$key] = SchemaPersonOrgBase::processedTestValue($items[$key]);
          break;

        case 'acceptedAnswer':
        case 'suggestedAnswer':
          $items[$key] = SchemaAnswerBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
