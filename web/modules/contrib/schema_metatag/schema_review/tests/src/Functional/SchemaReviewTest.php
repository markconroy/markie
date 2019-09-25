<?php

namespace Drupal\Tests\schema_review\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Review tags work correctly.
 *
 * @group schema_metatag
 * @group schema_review
 */
class SchemaReviewTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_review'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_review';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_review\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_review_review_rating' => 'SchemaReviewReviewRating',
    'schema_review_review_body' => 'SchemaReviewReviewBody',
    'schema_review_date_published' => 'SchemaReviewDatePublished',
    'schema_review_item_reviewed' => 'SchemaReviewItemReviewed',
    'schema_review_name' => 'SchemaReviewName',
    'schema_review_author' => 'SchemaReviewAuthor',
    'schema_review_type' => 'SchemaReviewType',
  ];

}
