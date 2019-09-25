<?php

namespace Drupal\Tests\schema_how_to\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag HowTo tags work correctly.
 *
 * @group schema_metatag
 * @group schema_how_to
 */
class SchemaHowToTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_how_to'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_how_to';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_how_to\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_how_to_type' => 'SchemaHowToType',
    'schema_how_to_name' => 'SchemaHowToName',
    'schema_how_to_step' => 'SchemaHowToStep',
    'schema_how_to_description' => 'SchemaHowToDescription',
    'schema_how_to_image' => 'SchemaHowToImage',
    'schema_how_to_estimated_cost' => 'SchemaHowToEstimatedCost',
    'schema_how_to_supply' => 'SchemaHowToSupply',
    'schema_how_to_tool' => 'SchemaHowToTool',
  ];

}
