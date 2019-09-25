<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'schema_movie_director' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_director",
 *   label = @Translation("director"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. The name of Director."),
 *   name = "director",
 *   group = "schema_movie",
 *   weight = 5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMovieDirector extends SchemaPersonOrgBase {

}
