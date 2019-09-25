<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Offer trait.
 */
trait SchemaOfferTrait {

  use SchemaCountryTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaCountryTrait;
  }

  /**
   * Form keys.
   */
  public static function offerFormKeys() {
    return [
      '@type',
      '@id',
      'price',
      'priceCurrency',
      'url',
      'availability',
      'availabilityStarts',
      'availabilityEnds',
      'itemCondition',
      'validFrom',
      'category',
      'eligibleRegion',
      'ineligibleRegion',
    ];
  }

  /**
   * The form element.
   */
  public function offerForm($input_values) {

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
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Offer' => $this->t('Offer'),
      ],
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#weight' => -10,
    ];

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t('Globally unique ID of the work in the form of a URL. It does not have to be a working link.'),
    ];

    $form['price'] = [
      '#type' => 'textfield',
      '#title' => $this->t('price'),
      '#default_value' => !empty($value['price']) ? $value['price'] : '',
      '#maxlength' => 255,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t('The numeric price of the offer.'),
    ];

    $form['priceCurrency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('priceCurrency'),
      '#default_value' => !empty($value['priceCurrency']) ? $value['priceCurrency'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The three-letter currency code (e.g. USD) in which the price is displayed.'),
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The URL to the store where the offer can be acquired.'),
    ];

    $form['itemCondition'] = [
      '#type' => 'textfield',
      '#title' => $this->t('condition'),
      '#default_value' => !empty($value['itemCondition']) ? $value['itemCondition'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The condition of this item—for example Damaged Condition, New Condition, Used Condition, Refurbished Condition.'),
    ];

    $form['availability'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availability'),
      '#default_value' => !empty($value['availability']) ? $value['availability'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The availability of this item—for example In stock, Out of stock, Pre-order, etc.'),
    ];

    $form['availabilityStarts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availabilityStarts'),
      '#default_value' => !empty($value['availabilityStarts']) ? $value['availabilityStarts'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Date when the action is available, in ISO 8601 format.'),
    ];

    $form['availabilityEnds'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availabilityEnds'),
      '#default_value' => !empty($value['availabilityEnds']) ? $value['availabilityEnds'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Date after which the item is no longer available, in ISO 8601 format.'),
    ];

    $form['validFrom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('validFrom'),
      '#default_value' => !empty($value['validFrom']) ? $value['validFrom'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The date when the item becomes valid.'),
    ];

    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('category'),
      '#default_value' => !empty($value['category']) ? $value['category'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("One of the following values: 'rental', 'purchase', 'subscription', 'externalSubscription', 'free'."),
    ];

    $input_values = [
      'title' => $this->t('eligibleRegion'),
      'description' => "The region where the offer is valid.",
      'value' => !empty($value['eligibleRegion']) ? $value['eligibleRegion'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[eligibleRegion]',
    ];
    $form['eligibleRegion'] = static::countryForm($input_values);

    // Pivot the country element.
    $form['eligibleRegion']['pivot'] = $this->pivotForm($value);
    $selector = ':input[name="' . $visibility_selector . '[eligibleRegion][@type]"]';
    $form['eligibleRegion']['pivot']['#states'] = ['invisible' => [$selector => ['value' => '']]];

    $input_values = [
      'title' => $this->t('ineligibleRegion'),
      'description' => "The region where the offer is not valid.",
      'value' => !empty($value['ineligibleRegion']) ? $value['ineligibleRegion'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[ineligibleRegion]',
    ];
    $form['ineligibleRegion'] = static::countryForm($input_values);

    // Pivot the country element.
    $form['ineligibleRegion']['pivot'] = $this->pivotForm($value);
    $selector = ':input[name="' . $visibility_selector . '[ineligibleRegion][@type]"]';
    $form['ineligibleRegion']['pivot']['#states'] = ['invisible' => [$selector => ['value' => '']]];

    $keys = static::offerFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
