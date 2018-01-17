<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org PostalAddress items should extend this class.
 */
abstract class SchemaAddressBase extends SchemaNameBase {

  /**
   * Traits provide re-usable form elements.
   */
  use SchemaAddressTrait;
  use SchemaPivotTrait;

  /**
   * The top level keys on this form.
   */
  public function form_keys() {
    return ['pivot'] + $this->postal_address_form_keys();
  }

  /**
   * Generate a form element for this meta tag.
   *
   * We need multiple values, so create a tree of values and
   * stored the serialized value as a string.
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

    $form = $this->postal_address_form($input_values);

    $form['pivot'] = $this->pivot_form($value);
    $form['pivot']['#states'] = ['invisible' => [
      ':input[name="' . $input_values['visibility_selector'] . '"]' => [
			  'value' => '']
      ]
    ];

    return $form;
  }

}
