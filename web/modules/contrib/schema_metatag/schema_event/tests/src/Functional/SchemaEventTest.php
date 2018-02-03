<?php

namespace Drupal\Tests\schema_event\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_event
 */
class SchemaEventTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_event'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_event';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_event\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_event_actor' => 'SchemaEventActor',
    'schema_event_aggregate_rating' => 'SchemaEventAggregateRating',
    'schema_event_description' => 'SchemaEventDescription',
    'schema_event_door_time' => 'SchemaEventDoorTime',
    'schema_event_end_date' => 'SchemaEventEndDate',
    'schema_event_image' => 'SchemaEventImage',
    'schema_event_is_accessible_for_free' => 'SchemaEventIsAccessibleForFree',
    'schema_event_location' => 'SchemaEventLocation',
    'schema_event_name' => 'SchemaEventName',
    'schema_event_offers' => 'SchemaEventOffers',
    'schema_event_performer' => 'SchemaEventPerformer',
    'schema_event_start_date' => 'SchemaEventStartDate',
    'schema_event_type' => 'SchemaEventType',
    'schema_event_url' => 'SchemaEventUrl',
  ];

}
