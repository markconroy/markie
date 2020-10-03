<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org ProgramMembership trait.
 */
trait SchemaProgramMembershipTrait {

  use SchemaPersonOrgTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
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
  public function programMembershipForm($input_values) {

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
        'ProgramMembership' => $this->t('ProgramMembership'),
      ],
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('name'),
      '#default_value' => !empty($value['name']) ? $value['name'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The name of the item."),
      '#states' => $visibility,
    ];

    $form['programName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('programName'),
      '#default_value' => !empty($value['programName']) ? $value['programName'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The program providing the membership."),
      '#states' => $visibility,
    ];

    $form['alternateName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('alternateName'),
      '#default_value' => !empty($value['alternateName']) ? $value['alternateName'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("An alias for the item."),
      '#states' => $visibility,
    ];

    $form['membershipNumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t('membershipNumber'),
      '#default_value' => !empty($value['membershipNumber']) ? $value['membershipNumber'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("A unique identifier for the membership."),
      '#states' => $visibility,
    ];

    $form['identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('identifier'),
      '#default_value' => !empty($value['identifier']) ? $value['identifier'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The identifier property represents any kind of identifier for any kind of Thing, such as ISBNs, GTIN codes, UUIDs etc. Schema.org provides dedicated properties for representing many of these, either as textual strings or as URL (URI) links."),
      '#states' => $visibility,
    ];

    $form['additionalType'] = [
      '#type' => 'textfield',
      '#title' => $this->t('additionalType'),
      '#default_value' => !empty($value['additionalType']) ? $value['additionalType'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("An additional type for the item, typically used for adding more specific types from external vocabularies in microdata syntax. This is a relationship between something and a class that the thing is in."),
      '#states' => $visibility,
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('description'),
      '#default_value' => !empty($value['description']) ? $value['description'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("A description of the item."),
      '#states' => $visibility,
    ];

    $form['disambiguatingDescription'] = [
      '#type' => 'textfield',
      '#title' => $this->t('disambiguatingDescription'),
      '#default_value' => !empty($value['disambiguatingDescription']) ? $value['disambiguatingDescription'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("A sub property of description. A short description of the item used to disambiguate from other, similar items. Information from other properties (in particular, name) may be necessary for the description to be useful for disambiguation."),
      '#states' => $visibility,
    ];

    $form['mainEntityOfPage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('mainEntityOfPage'),
      '#default_value' => !empty($value['mainEntityOfPage']) ? $value['mainEntityOfPage'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("If this is the main content of the page, provide url of the page. Only one object on each page should be marked as the main entity of the page."),
      '#states' => $visibility,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("URL of the item."),
      '#states' => $visibility,
    ];

    $form['sameAs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sameAs'),
      '#default_value' => !empty($value['sameAs']) ? $value['sameAs'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Url linked to the web site, such as wikipedia page or social profiles. Multiple values may be used, separated by a comma. Note: Tokens that return multiple values will be handled automatically."),
      '#states' => $visibility,
    ];

    $input_values = [
      'title' => $this->t('hostingOrganization'),
      'description' => "The organization (airline, travelers' club, etc.) the membership is made with.",
      'value' => !empty($value['hostingOrganization']) ? $value['hostingOrganization'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[hostingOrganization]',
    ];
    $form['hostingOrganization'] = $this->personOrgForm($input_values);
    $form['hostingOrganization']['#states'] = $visibility;

    $input_values = [
      'title' => $this->t('member'),
      'description' => "A member of an Organization or a ProgramMembership. Organizations can be members of organizations; ProgramMembership is typically for individuals.",
      'value' => !empty($value['member']) ? $value['member'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[member]',
    ];
    $form['member'] = $this->personOrgForm($input_values);
    $form['member']['#states'] = $visibility;

    $input_values = [
      'title' => $this->t('image'),
      'description' => "An image of the item.",
      'value' => !empty($value['image']) ? $value['image'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[image]',
    ];
    $form['image'] = $this->imageForm($input_values);
    $form['image']['#states'] = $visibility;

    return $form;
  }

}
