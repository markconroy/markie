<?php

namespace Drupal\Tests\schema_web_site\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_web_site
 */
class SchemaWebSiteTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_web_site'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_web_site';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_web_site\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_web_site_id' => 'SchemaWebSiteId',
    'schema_web_site_name' => 'SchemaWebSiteName',
    'schema_web_site_publisher' => 'SchemaWebSitePublisher',
    'schema_web_site_type' => 'SchemaWebSiteType',
    'schema_web_site_url' => 'SchemaWebSiteUrl',
  ];

}
