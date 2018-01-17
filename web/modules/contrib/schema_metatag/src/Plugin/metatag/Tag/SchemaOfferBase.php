<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Provides a plugin for the 'schema_offer_base' meta tag.
 */
abstract class SchemaOfferBase extends SchemaNameBase {

  /**
   * Traits provide re-usable form elements.
   */
  use SchemaOfferTrait;
  use SchemaPivotTrait;

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $value = SchemaMetatagManager::unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector() . '[@type]',
    ];

    $form = $this->offer_form($input_values);

    $form['pivot'] = $this->pivot_form($value);
    $form['pivot']['#states'] = ['invisible' => [
      ':input[name="' . $input_values['visibility_selector'] . '"]' => [
			  'value' => '']
      ]
    ];

    return $form;
  }

}
