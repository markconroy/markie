<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Person/Organization trait.
 */
trait SchemaPersonOrgTrait {

  use SchemaImageTrait;

  /**
   * Form keys.
   */
  public static function personOrgFormKeys() {
    return [
      '@type',
      '@id',
      'name',
      'url',
      'sameAs',
      'logo',
    ];
  }

  /**
   * Input values.
   */
  public function personOrgInputValues() {
    return [
      'title' => '',
      'description' => '',
      'value' => [],
      '#required' => FALSE,
      'visibility_selector' => '',
    ];
  }

  /**
   * The form element.
   */
  public function personOrgForm($input_values) {

    $input_values += $this->personOrgInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name=' . $this->visibilitySelector() . '[@type]]';
    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $org_visibility = ['visible' => [$selector => ['value' => 'Organization']]];

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
        'Person' => $this->t('Person'),
        'Organization' => $this->t('Organization'),
      ],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
    ];

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Globally unique @id of the person or organization, usually a url, used to to link other properties to this object."),
      '#states' => $visibility,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Name of the person or organization."),
      '#attributes' => ['placeholder' => '[node:author:display-name]'],
      '#states' => $visibility,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Absolute URL of the canonical Web page, like the URL of the author's profile page or the organization's official website."),
      '#attributes' => ['placeholder' => '[node:author:url]'],
      '#states' => $visibility,
    ];

    $form['sameAs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sameAs'),
      '#default_value' => !empty($value['sameAs']) ? $value['sameAs'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t("Comma separated list of URLs for the person's or organization's official social media profile page(s)."),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('Logo'),
      'description' => 'The logo of the organization. For AMP pages, Google requires a image no larger than 600 x 60.',
      'value' => !empty($value['logo']) ? $value['logo'] : [],
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->getPluginId() . '[logo][@type]',
    ];

    // Display the logo only for Organization.
    $form['logo'] = $this->imageForm($input_values);
    $form['logo']['#states'] = $org_visibility;

    return $form;
  }

}
