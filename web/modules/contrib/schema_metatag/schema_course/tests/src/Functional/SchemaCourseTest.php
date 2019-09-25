<?php

namespace Drupal\Tests\schema_course\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_course
 */
class SchemaCourseTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_course'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_course';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_course\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_course_course_code' => 'SchemaCourseCourseCode',
    'schema_course_course_prerequisites' => 'SchemaCourseCoursePrerequisites',
    'schema_course_description' => 'SchemaCourseDescription',
    'schema_course_educational_credential_awarded' => 'SchemaCourseEducationalCredentialAwarded',
    'schema_course_name' => 'SchemaCourseName',
    'schema_course_provider' => 'SchemaCourseProvider',
    'schema_course_type' => 'SchemaCourseType',
  ];

}
