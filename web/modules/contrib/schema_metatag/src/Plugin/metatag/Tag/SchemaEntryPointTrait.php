<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org EntryPoint trait.
 */
trait SchemaEntryPointTrait {

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
  public function entryPointForm($input_values) {

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
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'EntryPoint' => $this->t('EntryPoint'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['urlTemplate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('urlTemplate'),
      '#default_value' => !empty($value['urlTemplate']) ? $value['urlTemplate'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("An url template (RFC6570) that will be used to construct the target of the execution of the action, i.e. http://www.example.com/forrest_gump?autoplay=true."),
      '#states' => $visibility,
    ];

    $form['actionPlatform'] = [
      '#type' => 'textfield',
      '#title' => $this->t('actionPlatform'),
      '#default_value' => !empty($value['actionPlatform']) ? $value['actionPlatform'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Comma-separated list of the high level platform(s) where the Action can be performed for the given URL. Examples: http://schema.org/DesktopWebPlatform, http://schema.org/MobileWebPlatform, http://schema.org/IOSPlatform, http://schema.googleapis.com/GoogleVideoCast."),
      '#states' => $visibility,
    ];

    $form['inLanguage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('inLanguage'),
      '#default_value' => !empty($value['inLanguage']) ? $value['inLanguage'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The BCP-47 language code of this item, e.g. 'ja' is Japanese, or 'en-US' for American English."),
      '#states' => $visibility,
    ];

    return $form;
  }

}
