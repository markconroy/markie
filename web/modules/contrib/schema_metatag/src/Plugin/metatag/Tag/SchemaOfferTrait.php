<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Offer trait.
 */
trait SchemaOfferTrait {

  use SchemaCountryTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaCountryTrait;
  }

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
  public function offerForm($input_values) {

    $input_values += $this->schemaMetatagManager()->defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $visibility_selector = $input_values['visibility_selector'];
    $selector = ':input[name="' . $visibility_selector . '[@type]"]';
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
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Offer' => $this->t('Offer'),
        'AggregateOffer' => $this->t('AggregateOffer'),
      ],
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#weight' => -10,
    ];

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Globally unique ID of the item in the form of a URL. It does not have to be a working link.'),
      '#states' => $visibility,
    ];

    $form['price'] = [
      '#type' => 'textfield',
      '#title' => $this->t('price'),
      '#default_value' => !empty($value['price']) ? $value['price'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('REQUIRED BY GOOGLE for Offer. The numeric price of the offer. Do not include dollar sign.'),
      '#states' => $visibility,
    ];

    $form['offerCount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('offerCount'),
      '#default_value' => !empty($value['offerCount']) ? $value['offerCount'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('RECOMMEND BY GOOGLE for AggregateOffer. The number of offers.'),
      '#states' => $visibility,
    ];

    $form['lowPrice'] = [
      '#type' => 'textfield',
      '#title' => $this->t('lowPrice'),
      '#default_value' => !empty($value['lowPrice']) ? $value['lowPrice'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('REQUIRED BY GOOGLE for AggregateOffer. The lowest price. Do not include dollar sign.'),
      '#states' => $visibility,
    ];

    $form['highPrice'] = [
      '#type' => 'textfield',
      '#title' => $this->t('highPrice'),
      '#default_value' => !empty($value['highPrice']) ? $value['highPrice'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('REQUIRED BY GOOGLE for AggregateOffer. The highest price. Do not include dollar sign.'),
      '#states' => $visibility,
    ];

    $form['priceCurrency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('priceCurrency'),
      '#default_value' => !empty($value['priceCurrency']) ? $value['priceCurrency'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('REQUIRED BY GOOGLE. The three-letter currency code (i.e. USD) in which the price is displayed.'),
      '#states' => $visibility,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The URL where the offer can be acquired.'),
      '#states' => $visibility,
    ];

    $form['itemCondition'] = [
      '#type' => 'textfield',
      '#title' => $this->t('condition'),
      '#default_value' => !empty($value['itemCondition']) ? $value['itemCondition'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('RECOMMENDED BY GOOGLE for Product Offer. The condition of this item. Valid options are https://schema.org/DamagedCondition, https://schema.org/NewCondition, https://schema.org/RefurbishedCondition, https://schema.org/UsedCondition.'),
      '#states' => $visibility,
    ];

    $form['availability'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availability'),
      '#default_value' => !empty($value['availability']) ? $value['availability'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('REQUIRED BY GOOGLE for Product Offer. The availability of this item. Valid options are https://schema.org/Discontinued, https://schema.org/InStock, https://schema.org/InStoreOnly, https://schema.org/LimitedAvailability, https://schema.org/OnlineOnly, https://schema.org/OutOfStock, https://schema.org/PreOrder, https://schema.org/PreSale, https://schema.org/SoldOut.'),
      '#states' => $visibility,
    ];

    $form['availabilityStarts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availabilityStarts'),
      '#default_value' => !empty($value['availabilityStarts']) ? $value['availabilityStarts'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The end of the availability of the product or service included in the offer, in ISO 8601 format, i.e. 2024-05-21T12:00.'),
      '#states' => $visibility,
    ];

    $form['availabilityEnds'] = [
      '#type' => 'textfield',
      '#title' => $this->t('availabilityEnds'),
      '#default_value' => !empty($value['availabilityEnds']) ? $value['availabilityEnds'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('Date after which the item is no longer available, in ISO 8601 format, i.e. 2024-05-21T12:00.'),
      '#states' => $visibility,
    ];

    $form['validFrom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('validFrom'),
      '#default_value' => !empty($value['validFrom']) ? $value['validFrom'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The date when the item becomes valid, i.e. 2024-05-21T12:00.'),
      '#states' => $visibility,
    ];

    $form['priceValidUntil'] = [
      '#type' => 'textfield',
      '#title' => $this->t('priceValidUntil'),
      '#default_value' => !empty($value['priceValidUntil']) ? $value['priceValidUntil'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t('The date after which the price will no longer be available, in ISO 8601 format, i.e. 2024-05-21T12:00.'),
      '#states' => $visibility,
    ];

    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('category'),
      '#default_value' => !empty($value['category']) ? $value['category'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Values like: 'rental', 'purchase', 'subscription', 'externalSubscription', 'free'."),
      '#multiple' => TRUE,
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('eligibleRegion'),
      'description' => "The region where the offer is valid.",
      'value' => !empty($value['eligibleRegion']) ? $value['eligibleRegion'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[eligibleRegion]',
    ];
    $form['eligibleRegion'] = $this->countryForm($input_values);

    // Pivot the country element.
    $form['eligibleRegion']['pivot'] = $this->pivotForm($value);
    $selector = ':input[name="' . $visibility_selector . '[eligibleRegion][@type]"]';
    $form['eligibleRegion']['pivot']['#states'] = ['invisible' => [$selector => ['value' => '']]];
    $form['eligibleRegion']['#states'] = $visibility;

    $input_values = [
      'title' => $this->t('ineligibleRegion'),
      'description' => "The region where the offer is not valid.",
      'value' => !empty($value['ineligibleRegion']) ? $value['ineligibleRegion'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[ineligibleRegion]',
    ];
    $form['ineligibleRegion'] = $this->countryForm($input_values);
    $form['ineligibleRegion']['#states'] = $visibility;

    // Pivot the country element.
    $form['ineligibleRegion']['pivot'] = $this->pivotForm($value);
    $selector = ':input[name="' . $visibility_selector . '[ineligibleRegion][@type]"]';
    $form['ineligibleRegion']['pivot']['#states'] = ['invisible' => [$selector => ['value' => '']]];

    return $form;
  }

}
