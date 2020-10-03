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
    'schema_product_url' => 'SchemaProductUrl',
    'schema_product_category' => 'SchemaProductCategory',
    'schema_product_sku' => 'SchemaProductSku',
    'schema_product_gtin8' => 'SchemaProductGtin8',
    'schema_product_gtin12' => 'SchemaProductGtin12',
    'schema_product_gtin13' => 'SchemaProductGtin13',
    'schema_product_gtin14' => 'SchemaProductGtin14',
    'schema_product_isbn' => 'SchemaProductIsbn',
    'schema_product_mpn' => 'SchemaProductMpn',
  ];

}
