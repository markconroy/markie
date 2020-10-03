<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Provides a plugin for the 'schema_offer_base' meta tag.
 */
class SchemaOfferBase extends SchemaNameBase {

  use SchemaOfferTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $value = $this->schemaMetatagManager()->unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
    ];

    $form = $this->offerForm($input_values);

    if (empty($this->multiple())) {
      unset($form['pivot']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = [
      '@type',
      '@id',
      'price',
      'priceCurrency',
      'lowPrice',
      'highPrice',
      'offerCount',
      'url',
      'availability',
      'availabilityStarts',
      'availabilityEnds',
      'itemCondition',
      'validFrom',
      'category',
      'eligibleRegion',
      'ineligibleRegion',
      'priceValidUntil',
    ];
    foreach ($keys as $key) {
      switch ($key) {
        case '@type':
          $items[$key] = 'Offer';
          break;

        case 'eligibleRegion':
        case 'ineligibleRegion':
          $items[$key] = SchemaCountryBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(2, ' ');
          break;

      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function processedTestValue($items) {
    foreach ($items as $key => $value) {
      switch ($key) {
        case 'eligibleRegion':
        case 'ineligibleRegion':
          $items[$key] = SchemaCountryBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;
  }

}
