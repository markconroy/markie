<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Event trait.
 */
trait SchemaEventTrait {

  use SchemaPlaceTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaPlaceTrait;
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
  public function eventForm($input_values) {

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
      '#states' => $visibility,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Name of the Event."),
      '#states' => $visibility,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Absolute URL of the canonical Web page for the Event."),
      '#states' => $visibility,
    ];

    $form['startDate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('startDate'),
      '#default_value' => !empty($value['startDate']) ? $value['startDate'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Start date of the Event."),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('Location'),
      'description' => "The location of the event.",
      'value' => !empty($value['location']) ? $value['location'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[location]',
    ];
    $form['location'] = $this->placeForm($input_values);
    $form['location']['#states'] = $visibility;

    return $form;
  }

}
