<?php

namespace Drupal\Tests\schema_service\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_place
 */
class SchemaPlaceTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_place'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_place';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_place\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_place_name' => 'SchemaPlaceName',
    'schema_place_description' => 'SchemaPlaceDescription',
    'schema_place_telephone' => 'SchemaPlaceTelephone',
    'schema_place_image' => 'SchemaPlaceImage',
    'schema_place_type' => 'SchemaPlaceType',
    'schema_place_address' => 'SchemaPlaceAddress',
    'schema_place_geo' => 'SchemaPlaceGeo',
    'schema_place_url' => 'SchemaPlaceUrl',
  ];

}
