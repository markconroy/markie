<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Event trait.
 */
trait SchemaEventTrait {

  use SchemaPlaceTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaPlaceTrait;
  }

  /**
   * Form keys.
   */
  public static function eventFormKeys() {
    return [
      '@type',
      '@id',
      'name',
      'url',
      'startDate',
      'location',
    ];
  }

  /**
   * The form element.
   */
  public function eventForm($input_values) {

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
        'Event' => $this->t('Event'),
        'PublicationEvent' => $this->t('PublicationEvent'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['@id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('@id'),
      '#default_value' => !empty($value['@id']) ? $value['@id'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Globally unique @id of the Event, usually a url, used to to link other properties to this object."),
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Name of the Event."),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Absolute URL of the canonical Web page for the Event."),
    ];

    $form['startDate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('startDate'),
      '#default_value' => !empty($value['startDate']) ? $value['startDate'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Start date of the Event."),
    ];

    $input_values = [
      'title' => $this->t('Location'),
      'description' => "The location of the event.",
      'value' => !empty($value['location']) ? $value['location'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[location]',
    ];
    $form['location'] = static::placeForm($input_values);

    $keys = static::eventFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }

    return $form;
  }

}
