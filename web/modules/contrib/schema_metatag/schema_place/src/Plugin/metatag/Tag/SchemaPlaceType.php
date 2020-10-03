<?php

namespace Drupal\schema_place\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_place_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The type of place."),
 *   name = "@type",
 *   group = "schema_place",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPlaceType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
      'Place',
      '- Accommodation',
      '-- Apartment',
      '-- CampingPitch',
      '-- House',
      '--- SingleFamilyResidence',
      '-- Room',
      '--- HotelRoom',
      '--- MeetingRoom',
      '-- Suite',
      '- AdministrativeArea',
      '-- City',
      '-- Country',
      '-- SchoolDistrict',
      '-- State',
      '- CivicStructure',
      '-- Airport',
      '-- Aquarium',
      '-- Beach',
      '-- Bridge',
      '-- BusStation',
      '-- BusStop',
      '-- Campground',
      '-- Cemetery',
      '-- Crematorium',
      '-- EducationalOrganization',
      '-- EventVenue',
      '-- FireStation',
      '-- GovernmentBuilding',
      '--- CityHall',
      '--- Courthouse',
      '--- DefenceEstablishment',
      '--- Embassy',
      '--- LegislativeBuilding',
      '-- Hospital',
      '-- MovieTheater',
      '-- Museum',
      '-- MusicVenue',
      '-- Park',
      '-- ParkingFacility',
      '-- PerformingArtsTheater',
      '-- PlaceOfWorship',
      '--- BuddhistTemple',
      '--- Church',
      '---- CatholicChurch',
      '--- HinduTemple',
      '--- Mosque',
      '--- Synagogue',
      '-- Playground',
      '-- PoliceStation',
      '-- PublicToilet',
      '-- RVPark',
      '-- StadiumOrArena',
      '-- SubwayStation',
      '-- TaxiStand',
      '-- TrainStation',
      '-- Zoo',
      '- Landform',
      '-- BodyOfWater',
      '--- Canal',
      '--- LakeBodyOfWater',
      '--- OceanBodyOfWater',
      '--- Pond',
      '--- Reservoir',
      '--- RiverBodyOfWater',
      '--- SeaBodyOfWater',
      '--- Waterfall',
      '-- Continent',
      '-- Mountain',
      '-- Volcano',
      '- LandmarksOrHistoricalBuildings',
      '- LocalBusiness',
      '- Residence',
      '--- ApartmentComplex',
      '--- GatedResidenceCommunity',
      '- TouristAttraction',
      '- TouristDestination',
    ];
  }

}
