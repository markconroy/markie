<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Speakable trait.
 */
trait SchemaSpeakableTrait {

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
  public function speakableForm($input_values) {
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

    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'SpeakableSpecification' => $this->t('SpeakableSpecification'),
      ],
      '#description' => $this->t('Please provide either xpath or cssSelector, not both.'),
    ];

    $form['xpath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('xpath'),
      '#default_value' => !empty($value['xpath']) ? $value['xpath'] : '',
      '#description' => $this->t('Separate xpaths by comma, as in: @example',
        ['@example' => '/html/head/title, /html/head/meta[@name=\'description\']/@content']
      ),
      '#states' => $visibility,
    ];

    $form['cssSelector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('cssSelector'),
      '#default_value' => !empty($value['cssSelector']) ? $value['cssSelector'] : '',
      '#description' => $this->t('Separate selectors by comma, as in @example',
        ['@example' => '#title, #summary']
      ),
      '#states' => $visibility,
    ];

    return $form;
  }

}
