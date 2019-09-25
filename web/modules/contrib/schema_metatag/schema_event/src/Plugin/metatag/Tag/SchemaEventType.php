<?php

namespace Drupal\schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

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
 *   description = @Translation("REQUIRED. The type of event."),
 *   name = "@type",
 *   group = "schema_event",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaEventType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
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
      '- BroadcastEvent',
      '- OnDemandEvent',
      'SaleEvent',
      'ScreeningEvent',
      'SocialEvent',
      'SportsEvent',
      'TheaterEvent',
      'VisualArtsEvent',
    ];
  }

}
