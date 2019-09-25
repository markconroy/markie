<?php

namespace Drupal\Tests\schema_person\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_person
 */
class SchemaPersonTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_person'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_person';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_person\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_person_additional_name' => 'SchemaPersonAdditionalName',
    'schema_person_address' => 'SchemaPersonAddress',
    'schema_person_affiliation' => 'SchemaPersonAffiliation',
    'schema_person_alternate_name' => 'SchemaPersonAlternateName',
    'schema_person_birth_date' => 'SchemaPersonBirthDate',
    'schema_person_description' => 'SchemaPersonDescription',
    'schema_person_email' => 'SchemaPersonEmail',
    'schema_person_family_name' => 'SchemaPersonFamilyName',
    'schema_person_gender' => 'SchemaPersonGender',
    'schema_person_given_name' => 'SchemaPersonGivenName',
    'schema_person_image' => 'SchemaPersonImage',
    'schema_person_job_title' => 'SchemaPersonJobTitle',
    'schema_person_member_of' => 'SchemaPersonMemberOf',
    'schema_person_name' => 'SchemaPersonName',
    'schema_person_telephone' => 'SchemaPersonTelephone',
    'schema_person_type' => 'SchemaPersonType',
    'schema_person_url' => 'SchemaPersonUrl',
    'schema_person_same_as' => 'SchemaPersonSameAs',
    'schema_person_works_for' => 'SchemaPersonWorksFor',
    'schema_person_contact_point' => 'SchemaPersonContactPoint',
    'schema_person_brand' => 'SchemaPersonBrand',
  ];

}
