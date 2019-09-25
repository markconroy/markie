<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'schema_movie_production_company' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_production_company",
 *   label = @Translation("productionCompany"),
 *   description = @Translation("The production company or studio that made the work."),
 *   name = "productionCompany",
 *   group = "schema_movie",
 *   weight = 15,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMovieProductionCompany extends SchemaPersonOrgBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
