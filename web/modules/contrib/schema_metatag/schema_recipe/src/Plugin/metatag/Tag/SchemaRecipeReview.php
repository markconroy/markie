<?php

namespace Drupal\schema_recipe\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaReviewBase;

/**
 * Provides a plugin for the 'schema_recipe_review' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_recipe_review",
 *   label = @Translation("review"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. Reviews of this recipe."),
 *   name = "review",
 *   group = "schema_recipe",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaRecipeReview extends SchemaReviewBase {

}
