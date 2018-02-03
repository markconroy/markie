<?php

namespace Drupal\Tests\schema_service\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_service
 */
class SchemaServiceTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_service'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_service';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_service\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_service_aggregate_rating' => 'SchemaServiceAggregateRating',
    'schema_service_description' => 'SchemaServiceDescription',
    'schema_service_image' => 'SchemaServiceImage',
    'schema_service_name' => 'SchemaServiceName',
    'schema_service_offers' => 'SchemaServiceOffers',
    'schema_service_type' => 'SchemaServiceType',
  ];

}
