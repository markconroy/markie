<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Speakable trait.
 */
trait SchemaSpeakableTrait {

  /**
   * Form keys.
   */
  public static function speakableFormKeys() {
    return [
      '@type',
      'xpath',
      'cssSelector',
    ];
  }

  /**
   * The form element.
   */
  public function speakableForm($input_values) {
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

    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'SpeakableSpecification' => $this->t('SpeakableSpecification'),
      ],
      '#description' => 'Please provide either xpath or cssSelector, not both.',
    ];

    $form['xpath'] = [
      '#type' => 'textfield',
      '#title' => $this->t('xpath'),
      '#default_value' => !empty($value['xpath']) ? $value['xpath'] : '',
      '#description' => $this->t('Separate xpaths by comma, as in: @example',
        ['@example' => '/html/head/title, /html/head/meta[@name=\'description\']/@content']
      ),
    ];

    $form['cssSelector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('cssSelector'),
      '#default_value' => !empty($value['cssSelector']) ? $value['cssSelector'] : '',
      '#description' => $this->t('Separate selectors by comma, as in @example',
        ['@example' => '#title, #summary']
      ),
    ];

    $keys = self::speakableFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
