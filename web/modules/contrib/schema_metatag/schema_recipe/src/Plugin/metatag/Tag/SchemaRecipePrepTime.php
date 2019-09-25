<?php

namespace Drupal\schema_recipe\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaDurationBase;

/**
 * Provides a plugin for the 'prepTime' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_recipe_prep_time",
 *   label = @Translation("prepTime"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. The length of time it takes to prepare the recipe for dish, in ISO 8601 format."),
 *   name = "prepTime",
 *   group = "schema_recipe",
 *   weight = 3,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaRecipePrepTime extends SchemaDurationBase {

}
