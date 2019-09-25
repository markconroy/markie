<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'schema_movie_music_by' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_music_by",
 *   label = @Translation("musicBy"),
 *   description = @Translation("The composer of the work."),
 *   name = "musicBy",
 *   group = "schema_movie",
 *   weight = 15,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMovieMusicBy extends SchemaPersonOrgBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
