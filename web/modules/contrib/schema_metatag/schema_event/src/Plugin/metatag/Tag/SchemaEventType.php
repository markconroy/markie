<?php

namespace Drupal\schema_event\Plugin\metatag\Tag;

use \Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_event_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of event (fixed by standard)."),
 *   name = "@type",
 *   group = "schema_event",
 *   weight = -5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventType extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $this->types(),
      '#default_value' => $this->value(),
    ];
    return $form;
  }


  /**
   * Return a list of organization types.
   */
  private function types() {
    $types = [
      'Event',
      'BusinessEvent',
      'ChildrensEvent',
      'ComedyEvent',
      'CourseInstance',
      'DanceEvent',
      'DeliveryEvent',
      'EducationEvent',
      'ExhibitionEvent',
      'Festival',
      'FoodEvent',
      'LiteraryEvent',
      'MusicEvent',
      'PublicationEvent',
      'SaleEvent',
      'ScreeningEvent',
      'SocialEvent',
      'SportsEvent',
      'TheaterEvent',
      'VisualArtsEvent',
    ];
    return array_combine($types, $types);
  }
}
