<?php

namespace Drupal\Tests\schema_article\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_article
 */
class SchemaArticleTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_article'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_article';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_article\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_article_about' => 'SchemaArticleAbout',
    'schema_article_author' => 'SchemaArticleAuthor',
    'schema_article_date_modified' => 'SchemaArticleDateModified',
    'schema_article_date_published' => 'SchemaArticleDatePublished',
    'schema_article_description' => 'SchemaArticleDescription',
    'schema_article_has_part' => 'SchemaArticleHasPart',
    'schema_article_headline' => 'SchemaArticleHeadline',
    'schema_article_image' => 'SchemaArticleImage',
    'schema_article_is_accessible_for_free' => 'SchemaArticleIsAccessibleForFree',
    'schema_article_main_entity_of_page' => 'SchemaArticleMainEntityOfPage',
    'schema_article_name' => 'SchemaArticleName',
    'schema_article_publisher' => 'SchemaArticlePublisher',
    'schema_article_type' => 'SchemaArticleType',
  ];

}
