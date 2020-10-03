<?php

namespace Drupal\Tests\schema_metatag\Functional;

/**
 * Tests that each of the SchemaMetatagTest Metatag base tags work correctly.
 *
 * @group schema_metatag
 * @group schema_metatag_base
 */
class SchemaMetatagTagsTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // This module.
    'schema_metatag_test',

    // Required to test the list element.
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_metatag_test';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_metatag_test\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_metatag_test_type' => 'SchemaMetatagTestType',
    'schema_metatag_test_address' => 'SchemaMetatagTestAddress',
    'schema_metatag_test_date' => 'SchemaMetatagTestDate',
    'schema_metatag_test_duration' => 'SchemaMetatagTestDuration',
    'schema_metatag_test_geo' => 'SchemaMetatagTestGeo',
    'schema_metatag_test_has_part' => 'SchemaMetatagTestHasPart',
    'schema_metatag_test_has_part_multiple' => 'SchemaMetatagTestHasPartMultiple',
    'schema_metatag_test_image' => 'SchemaMetatagTestImage',
    'schema_metatag_test_item_list_element' => 'SchemaMetatagTestItemListElement',
    'schema_metatag_test_main_entity_of_page' => 'SchemaMetatagTestMainEntityOfPage',
    'schema_metatag_test_name' => 'SchemaMetatagTestName',
    'schema_metatag_test_offer' => 'SchemaMetatagTestOffer',
    'schema_metatag_test_organization' => 'SchemaMetatagTestPersonOrg',
    'schema_metatag_test_aggregate_rating' => 'SchemaMetatagTestAggregateRating',
    'schema_metatag_test_review' => 'SchemaMetatagTestReview',
    'schema_metatag_test_place' => 'SchemaMetatagTestPlace',
    'schema_metatag_test_thing' => 'SchemaMetatagTestThing',
    'schema_metatag_test_event' => 'SchemaMetatagTestEvent',
    'schema_metatag_test_entry_point' => 'SchemaMetatagTestEntryPoint',
    'schema_metatag_test_action' => 'SchemaMetatagTestAction',
    'schema_metatag_test_creative_work' => 'SchemaMetatagTestCreativeWork',
    'schema_metatag_test_member_of' => 'SchemaMetatagTestMemberOf',
    'schema_metatag_test_opening_hours_specification' => 'SchemaMetatagTestOpeningHoursSpecification',
    'schema_metatag_test_nutrition_information' => 'SchemaMetatagTestNutritionInformation',
    'schema_metatag_test_contact_point' => 'SchemaMetatagTestContactPoint',
    'schema_metatag_test_speakable' => 'SchemaMetatagTestSpeakable',
    'schema_metatag_test_id_reference' => 'SchemaMetatagTestIdReference',
    'schema_metatag_test_answer' => 'SchemaMetatagTestAnswer',
    'schema_metatag_test_how_to_step' => 'SchemaMetatagTestHowToStep',
    'schema_metatag_test_question' => 'SchemaMetatagTestQuestion',
    'schema_metatag_test_government_service' => 'SchemaMetatagTestGovermentService',

  ];

}
