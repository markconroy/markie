<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org ContactPoint trait.
 */
trait SchemaContactPointTrait {

  use SchemaPivotTrait, SchemaPlaceTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaPlaceTrait;
  }

  /**
   * Form keys.
   */
  public static function contactPointFormKeys() {
    return [
      '@type',
      'areaServed',
      'availableLanguage',
      'contactType',
      'contactOption',
      'email',
      'faxnumber',
      'productSupported',
      'telephone',
      'url',
    ];
  }

  /**
   * Form element.
   *
   * @param array $input_values
   *
   * @return mixed
   */
  public function contactPointForm(array $input_values) {

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
        'ContactPoint' => $this->t('ContactPoint'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['telephone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('telephone'),
      '#default_value' => !empty($value['telephone']) ? $value['telephone'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('An internationalized version of the phone number, starting with the "+" symbol and country code (+1 in the US and Canada). Examples: "+1-800-555-1212", "+44-2078225951"'),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('URL of place, organization'),
    ];

    $form['availableLanguage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availableLanguage'),
      '#default_value' => !empty($value['availableLanguage']) ? $value['availableLanguage'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Details about the language spoken. Languages may be specified by their common English name. If omitted, the language defaults to English. Examples: "English, Spanish".'),
    ];

    $form['contactType'] = [
      '#type' => 'textfield',
      '#title' => $this->t('contactType'),
      '#default_value' => !empty($value['contactType']) ? $value['contactType'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('One of the following: customer service, technical support, billing support, bill payment, sales, reservations, credit card support, emergency, baggage tracking, roadside assistance, package tracking.'),
    ];

    $form['contactOption'] = [
      '#type' => 'textfield',
      '#title' => $this->t('contactOption'),
      '#default_value' => !empty($value['contactOption']) ? $value['contactOption'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('One of the following: HearingImpairedSupported, TollFree.'),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('areaServed'),
      'description' => 'The geographical region served by the number, specified as a AdministrativeArea. If omitted, the number is assumed to be global.',
      'value' => !empty($value['areaServed']) ? $value['areaServed'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[areaServed]',
    ];

    $form['areaServed'] = $this->placeForm($input_values);
    $form['areaServed']['#states'] = $visibility;

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('email'),
      '#default_value' => !empty($value['email']) ? $value['email'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Email address.'),
    ];

    $form['faxnumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t('faxnumber'),
      '#default_value' => !empty($value['faxnumber']) ? $value['faxnumber'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The fax number.'),
    ];

    $form['productSupported'] = [
      '#type' => 'textfield',
      '#title' => $this->t('productSupported'),
      '#default_value' => !empty($value['productSupported']) ? $value['productSupported'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The product or service this support contact point is related to (such as product support for a particular product line). This can be a specific product or product line (e.g. "iPhone") or a general category of products or services (e.g. "smartphones").'),
    ];

    $keys = static::contactPointFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;

  }

}
