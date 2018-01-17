<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Person/Org items should extend this class.
 */
abstract class SchemaPersonOrgBase extends SchemaNameBase {

  /**
   * Traits provide re-usable form elements.
   */
  use SchemaPersonOrgTrait;
  use SchemaPivotTrait;

  /**
   * The top level keys on this form.
   */
  function form_keys() {
    return ['pivot'] + $this->person_org_form_keys();
  }

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

    $form = $this->person_org_form($input_values);
    $form['pivot'] = $this->pivot_form($value);
    $form['pivot'] = $this->pivot_form($value);
    $form['pivot']['#states'] = ['invisible' => [
      ':input[name="' . $input_values['visibility_selector'] . '"]' => [
			  'value' => '']
      ]
    ];

    return $form;
  }

}
