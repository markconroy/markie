<?php

namespace Drupal\Tests\schema_organization\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_organization
 */
class SchemaOrganizationTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_organization'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_organization';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_organization\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_organization_address' => 'SchemaOrganizationAddress',
    'schema_organization_star_rating' => 'SchemaOrganizationStarRating',
    'schema_organization_aggregate_rating' => 'SchemaOrganizationAggregateRating',
    'schema_organization_review' => 'SchemaOrganizationReview',
    'schema_organization_geo' => 'SchemaOrganizationGeo',
    'schema_organization_id' => 'SchemaOrganizationId',
    'schema_organization_image' => 'SchemaOrganizationImage',
    'schema_organization_logo' => 'SchemaOrganizationLogo',
    'schema_organization_name' => 'SchemaOrganizationName',
    'schema_organization_price_range' => 'SchemaOrganizationPriceRange',
    'schema_organization_same_as' => 'SchemaOrganizationSameAs',
    'schema_organization_telephone' => 'SchemaOrganizationTelephone',
    'schema_organization_type' => 'SchemaOrganizationType',
    'schema_organization_url' => 'SchemaOrganizationUrl',
    'schema_organization_menu' => 'SchemaOrganizationMenu',
    'schema_organization_potential_action' => 'SchemaOrganizationPotentialAction',
    'schema_organization_member_of' => 'SchemaOrganizationMemberOf',
    'schema_organization_accepts_reservations' => 'SchemaOrganizationAcceptsReservations',
    'schema_organization_contact_point' => 'SchemaOrganizationContactPoint',
    'schema_organization_additional_type' => 'SchemaOrganizationAdditionalType',
    'schema_organization_brand' => 'SchemaOrganizationBrand',
  ];

}
