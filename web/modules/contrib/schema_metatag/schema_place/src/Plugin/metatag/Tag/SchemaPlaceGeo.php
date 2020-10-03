<?php

namespace Drupal\schema_place\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaGeoBase;

/**
 * Provides a plugin for the 'schema_place_geo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_place_geo",
 *   label = @Translation("geo"),
 *   description = @Translation("Geographic coordinates of the place."),
 *   name = "geo",
 *   group = "schema_place",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPlaceGeo extends SchemaGeoBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
