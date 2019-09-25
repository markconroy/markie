<?php

namespace Drupal\Tests\schema_image_object\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_image_object
 */
class SchemaImageObjectTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_image_object'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_image_object';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_image_object\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_image_object_aggregate_rating' => 'SchemaImageObjectAggregateRating',
    'schema_image_object_review' => 'SchemaImageObjectReview',
    'schema_image_object_description' => 'SchemaImageObjectDescription',
    'schema_image_object_height' => 'SchemaImageObjectHeight',
    'schema_image_object_name' => 'SchemaImageObjectName',
    'schema_image_object_type' => 'SchemaImageObjectType',
    'schema_image_object_url' => 'SchemaImageObjectUrl',
    'schema_image_object_width' => 'SchemaImageObjectWidth',
  ];

}
