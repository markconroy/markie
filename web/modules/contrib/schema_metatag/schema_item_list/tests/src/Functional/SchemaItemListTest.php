<?php

namespace Drupal\Tests\schema_item_list\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_item_list
 */
class SchemaItemListTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_item_list'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_item_list';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_item_list\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_item_list_item_list_element' => 'SchemaItemListItemListElement',
    'schema_item_list_main_entity_of_page' => 'SchemaItemListMainEntityOfPage',
    'schema_item_list_type' => 'SchemaItemListType',
  ];

}
