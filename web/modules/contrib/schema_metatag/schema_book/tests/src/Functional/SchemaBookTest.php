<?php

namespace Drupal\Tests\schema_book\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_book
 */
class SchemaBookTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_book'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_book';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_book\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_book_author' => 'SchemaBookAuthor',
    'schema_book_id' => 'SchemaBookId',
    'schema_book_name' => 'SchemaBookName',
    'schema_book_description' => 'SchemaBookDescription',
    'schema_book_same_as' => 'SchemaBookSameAs',
    'schema_book_type' => 'SchemaBookType',
    'schema_book_url' => 'SchemaBookUrl',
    'schema_book_work_example' => 'SchemaBookWorkExample',
    'schema_book_image' => 'SchemaBookImage',
    'schema_book_review' => 'SchemaBookReview',
    'schema_book_aggregate_rating' => 'SchemaBookAggregateRating',
  ];

}
