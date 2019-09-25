<?php

namespace Drupal\Tests\schema_job_posting\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_job_posting
 */
class SchemaJobPostingTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_job_posting'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_job_posting';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_job_posting\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_job_posting_base_salary' => 'SchemaJobPostingBaseSalary',
    'schema_job_posting_date_posted' => 'SchemaJobPostingDatePosted',
    'schema_job_posting_description' => 'SchemaJobPostingDescription',
    'schema_job_posting_employment_type' => 'SchemaJobPostingEmploymentType',
    'schema_job_posting_hiring_organization' => 'SchemaJobPostingHiringOrganization',
    'schema_job_posting_identifier' => 'SchemaJobPostingIdentifier',
    'schema_job_posting_industry' => 'SchemaJobPostingIndustry',
    'schema_job_posting_job_benefits' => 'SchemaJobPostingJobBenefits',
    'schema_job_posting_job_location' => 'SchemaJobPostingJobLocation',
    'schema_job_posting_occupational_category' => 'SchemaJobPostingOccupationalCategory',
    'schema_job_posting_qualifications' => 'SchemaJobPostingQualifications',
    'schema_job_posting_responsibilities' => 'SchemaJobPostingResponsibilities',
    'schema_job_posting_title' => 'SchemaJobPostingTitle',
    'schema_job_posting_type' => 'SchemaJobPostingType',
    'schema_job_posting_valid_through' => 'SchemaJobPostingValidThrough',
  ];

}
