<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org HowToStep trait.
 */
trait SchemaHowToStepTrait {

  use SchemaImageTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaImageTrait;
  }

  /**
   * Form keys.
   */
  public static function howToStepFormKeys() {
    return [
      '@type',
      'name',
      'text',
      'url',
      'image',
    ];
  }

  /**
   * The form element.
   */
  public function howToStepForm($input_values) {

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
        'HowToStep' => $this->t('HowToStep'),
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
      '#description' => $this->t("RECOMMENDED BY GOOGLE. The word or short phrase summarizing the step (for example, \"Attach wires to post\" or \"Dig\"). Don't use non-descriptive text."),
    ];

    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('text'),
      '#default_value' => !empty($value['text']) ? $value['text'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("REQUIRED BY GOOGLE. The full instruction text of this step."),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('RECOMMENDED BY GOOGLE. A URL that directly links to the step (if one is available). For example, an anchor link fragment.'),
    ];

    // Add nested objects.
    $input_values = [
      'title' => $this->t('image'),
      'description' => 'RECOMMENDED BY GOOGLE. An image of the step.',
      'value' => !empty($value['image']) ? $value['image'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $visibility_selector . '[image]',
    ];
    $form['image'] = $this->imageForm($input_values);

    // Add visibility settings to hide fields when the type is empty.
    $keys = static::howToStepFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
