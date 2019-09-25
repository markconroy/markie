<?php

namespace Drupal\Tests\schema_product\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_product
 */
class SchemaProductTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_product'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_product';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_product\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_product_aggregate_rating' => 'SchemaProductAggregateRating',
    'schema_product_review' => 'SchemaProductReview',
    'schema_product_description' => 'SchemaProductDescription',
    'schema_product_image' => 'SchemaProductImage',
    'schema_product_name' => 'SchemaProductName',
    'schema_product_offers' => 'SchemaProductOffers',
    'schema_product_type' => 'SchemaProductType',
    'schema_product_brand' => 'SchemaProductBrand',
  ];

}
