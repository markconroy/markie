<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Brand trait.
 */
trait SchemaBrandTrait {

  use SchemaImageTrait;

  /**
   * Form keys.
   */
  public static function brandFormKeys() {
    return [
      '@type',
      '@id',
      'name',
      'description',
      'url',
      'sameAs',
      'logo',
    ];
  }

  /**
   * The form elements.
   */
  public function brandForm($input_values) {

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
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Brand' => $this->t('Brand'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Globally unique @id of the brand, usually a url, used to to link other properties to this object."),
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Name of the brand."),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('description'),
      '#default_value' => !empty($value['description']) ? $value['description'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Description of the brand."),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Absolute URL of the canonical Web page, e.g. the URL of the brand's node or term page or brand website."),
    ];

    $form['sameAs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sameAs'),
      '#default_value' => !empty($value['sameAs']) ? $value['sameAs'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Comma separated list of URLs for the person's or organization's official social media profile page(s)."),
    ];

    $input_values = [
      'title' => $this->t('Logo'),
      'description' => $this->t('The URL of a logo that is representative of the organization, person, product or service. Review <a href="@logo" target="_blank">Google guidelines.</a>', [
        '@logo' => 'https://developers.google.com/search/docs/data-types/logo',
      ]),
      'value' => !empty($value['logo']) ? $value['logo'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $input_values['visibility_selector'] . '[logo]',
    ];

    // Display the logo for brand.
    $form['logo'] = $this->imageForm($input_values);

    $keys = static::brandFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
