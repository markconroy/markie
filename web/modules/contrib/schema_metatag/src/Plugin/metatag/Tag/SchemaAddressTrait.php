<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org PostalAddress trait.
 */
trait SchemaAddressTrait {

  /**
   * Form keys.
   */
  public static function postalAddressFormKeys() {
    return [
      '@type',
      'streetAddress',
      'addressLocality',
      'addressRegion',
      'postalCode',
      'addressCountry',
    ];
  }

  /**
   * Input values.
   */
  public function postalAddressInputValues() {
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
  public function postalAddressForm($input_values) {

    $input_values += $this->postalAddressInputValues();
    $value = $input_values['value'];

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
        'PostalAddress' => $this->t('PostalAddress'),
      ],
      '#required' => $input_values['#required'],
    ];

    $form['streetAddress'] = [
      '#type' => 'textfield',
      '#title' => $this->t('streetAddress'),
      '#default_value' => !empty($value['streetAddress']) ? $value['streetAddress'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The street address. For example, 1600 Amphitheatre Pkwy."),
    ];

    $form['addressLocality'] = [
      '#type' => 'textfield',
      '#title' => $this->t('addressLocality'),
      '#default_value' => !empty($value['addressLocality']) ? $value['addressLocality'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The locality. For example, Mountain View."),
    ];

    $form['addressRegion'] = [
      '#type' => 'textfield',
      '#title' => $this->t('addressRegion'),
      '#default_value' => !empty($value['addressRegion']) ? $value['addressRegion'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The region. For example, CA."),
    ];

    $form['postalCode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('postalCode'),
      '#default_value' => !empty($value['postalCode']) ? $value['postalCode'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The postal code. For example, 94043.'),
    ];

    $form['addressCountry'] = [
      '#type' => 'textfield',
      '#title' => $this->t('addressCountry'),
      '#default_value' => !empty($value['addressCountry']) ? $value['addressCountry'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The country. For example, USA. You can also provide the two-letter ISO 3166-1 alpha-2 country code.'),
    ];

    // Add #states to show/hide the fields based on the value of @type,
    // if a selector was provided.
    if (!empty($input_values['visibility_selector'])) {
      $keys = $this->postalAddressFormKeys();
      $selector = ':input[name="' . $input_values['visibility_selector'] . '"]';
      $visibility = ['visible' => [$selector => ['value' => 'PostalAddress']]];
      foreach ($keys as $key) {
        if ($key != '@type') {
          $form[$key]['#states'] = $visibility;
        }
      }
    }

    return $form;
  }

}
