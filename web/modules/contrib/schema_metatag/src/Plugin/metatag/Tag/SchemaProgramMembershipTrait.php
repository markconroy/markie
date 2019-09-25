<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org ProgramMembership trait.
 */
trait SchemaProgramMembershipTrait {

  use SchemaPersonOrgTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
  }

  /**
   * Form keys.
   */
  public static function programMembershipFormKeys() {
    return [
      '@type',
      'name',
      'programName',
      'alternateName',
      'hostingOrganization',
      'member',
      'membershipNumber',
      'identifier',
      'additionalType',
      'description',
      'disambiguatingDescription',
      'image',
      'mainEntityOfPage',
      'url',
      'sameAs',
    ];
  }

  /**
   * The form element.
   */
  public function programMembershipForm($input_values) {

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
    ];

    $form['programName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('programName'),
      '#default_value' => !empty($value['programName']) ? $value['programName'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The program providing the membership."),
    ];

    $form['alternateName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('alternateName'),
      '#default_value' => !empty($value['alternateName']) ? $value['alternateName'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("An alias for the item."),
    ];

    $input_values = [
      'title' => $this->t('hostingOrganization'),
      'description' => "The organization (airline, travelers' club, etc.) the membership is made with.",
      'value' => !empty($value['hostingOrganization']) ? $value['hostingOrganization'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[hostingOrganization]',
    ];
    $form['hostingOrganization'] = static::personOrgForm($input_values);

    $input_values = [
      'title' => $this->t('member'),
      'description' => "A member of an Organization or a ProgramMembership. Organizations can be members of organizations; ProgramMembership is typically for individuals.",
      'value' => !empty($value['member']) ? $value['member'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[member]',
    ];
    $form['member'] = static::personOrgForm($input_values);

    $form['membershipNumber'] = [
      '#type' => 'textfield',
      '#title' => $this->t('membershipNumber'),
      '#default_value' => !empty($value['membershipNumber']) ? $value['membershipNumber'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("A unique identifier for the membership."),
    ];

    $form['identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('identifier'),
      '#default_value' => !empty($value['identifier']) ? $value['identifier'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("The identifier property represents any kind of identifier for any kind of Thing, such as ISBNs, GTIN codes, UUIDs etc. Schema.org provides dedicated properties for representing many of these, either as textual strings or as URL (URI) links."),
    ];

    $form['additionalType'] = [
      '#type' => 'textfield',
      '#title' => $this->t('additionalType'),
      '#default_value' => !empty($value['additionalType']) ? $value['additionalType'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("An additional type for the item, typically used for adding more specific types from external vocabularies in microdata syntax. This is a relationship between something and a class that the thing is in."),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('description'),
      '#default_value' => !empty($value['description']) ? $value['description'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("A description of the item."),
    ];

    $form['disambiguatingDescription'] = [
      '#type' => 'textfield',
      '#title' => $this->t('disambiguatingDescription'),
      '#default_value' => !empty($value['disambiguatingDescription']) ? $value['disambiguatingDescription'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("A sub property of description. A short description of the item used to disambiguate from other, similar items. Information from other properties (in particular, name) may be necessary for the description to be useful for disambiguation."),
    ];

    $input_values = [
      'title' => $this->t('image'),
      'description' => "An image of the item.",
      'value' => !empty($value['image']) ? $value['image'] : [],
      '#required' => $input_values['#required'],
      'visibility_selector' => $visibility_selector . '[image]',
    ];
    $form['image'] = static::imageForm($input_values);

    $form['mainEntityOfPage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('mainEntityOfPage'),
      '#default_value' => !empty($value['mainEntityOfPage']) ? $value['mainEntityOfPage'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("If this is the main content of the page, provide url of the page. Only one object on each page should be marked as the main entity of the page."),
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('url'),
      '#default_value' => !empty($value['url']) ? $value['url'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("URL of the item."),
    ];

    $form['sameAs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('sameAs'),
      '#default_value' => !empty($value['sameAs']) ? $value['sameAs'] : '',
      '#maxlength' => 255,
      '#required' => $input_values['#required'],
      '#description' => $this->t("Url linked to the web site, such as wikipedia page or social profiles. Multiple values may be used, separated by a comma. Note: Tokens that return multiple values will be handled automatically."),
    ];

    $keys = static::programMembershipFormKeys();
    foreach ($keys as $key) {
      if ($key != '@type') {
        $form[$key]['#states'] = $visibility;
      }
    }
    return $form;
  }

}
