<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;

/**
 * Provides a plugin for the 'image' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_image",
 *   label = @Translation("image"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. The primary image for this work."),
 *   name = "image",
 *   group = "schema_movie",
 *   weight = 2,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMovieImage extends SchemaImageBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
