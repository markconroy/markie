<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaCreativeWorkBase;

/**
 * Provides a plugin for the 'partOfSeason' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_part_of_season",
 *   label = @Translation("partOfSeason"),
 *   description = @Translation("REQUIRED BY GOOGLE for TVEpisode."),
 *   name = "partOfSeason",
 *   group = "schema_movie",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMoviePartOfSeason extends SchemaCreativeWorkBase {


}
