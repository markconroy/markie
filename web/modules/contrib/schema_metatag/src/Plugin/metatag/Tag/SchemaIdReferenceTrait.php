<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Id Reference pivot trait.
 */
trait SchemaIdReferenceTrait {

  use SchemaPivotTrait;

  /**
   * Form keys.
   */
  public static function idFormKeys() {
    return [
      '@id',
    ];
  }

  /**
   * The form element.
   */
  public function idForm($input_values) {

    $input_values += SchemaMetatagManager::defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@id]"]';
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

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Globally unique @id of the related node, usually a url, used to to link other properties to this object."),
    ];

    $keys = static::idFormKeys();
    foreach ($keys as $key) {
      if ($key != '@id') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
