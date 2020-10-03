<?php

namespace Drupal\Tests\schema_special_announcement\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that the Schema Metatag SpecialAnnouncement tags work correctly.
 *
 * @group schema_metatag
 * @group schema_special_announcement
 */
class SchemaSpecialAnnouncementTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_special_announcement'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_special_announcement';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_special_announcement\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_special_announcement_type' => 'SchemaSpecialAnnouncementType',
    'schema_special_announcement_date_posted' => 'SchemaSpecialAnnouncementDatePosted',
    'schema_special_announcement_name' => 'SchemaSpecialAnnouncementName',
    'schema_special_announcement_text' => 'SchemaSpecialAnnouncementText',
    'schema_special_announcement_disease_prevention_info' => 'SchemaSpecialAnnouncementDiseasePreventionInfo',
    'schema_special_announcement_disease_spread_statistics' => 'SchemaSpecialAnnouncementDiseaseSpreadStatistics',
    'schema_special_announcement_getting_tested_info' => 'SchemaSpecialAnnouncementGettingTestedInfo',
    'schema_special_announcement_government_benefits_info' => 'SchemaSpecialAnnouncementGovernmentBenefitsInfo',
    'schema_special_announcement_news_updates_and_guidelines' => 'SchemaSpecialAnnouncementNewsUpdatesAndGuidelines',
    'schema_special_announcement_public_transport_closures_info' => 'SchemaSpecialAnnouncementPublicTransportClosuresInfo',
    'schema_special_announcement_quarantine_guidelines' => 'SchemaSpecialAnnouncementQuarantineGuidelines',
    'schema_special_announcement_school_closures_info' => 'SchemaSpecialAnnouncementSchoolClosuresInfo',
    'schema_special_announcement_travel_bans' => 'SchemaSpecialAnnouncementTravelBans',
    'schema_special_announcement_category' => 'SchemaSpecialAnnouncementCategory',
    'schema_special_announcement_expires' => 'SchemaSpecialAnnouncementExpires',
    'schema_special_announcement_spatial_coverage' => 'SchemaSpecialAnnouncementSpatialCoverage',
    'schema_special_announcement_announcement_location' => 'SchemaSpecialAnnouncementAnnouncementLocation',
    'schema_special_announcement_government_benefits_info' => 'SchemaSpecialAnnouncementGovernmentBenefitsInfo',
  ];

}
