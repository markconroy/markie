<?php

namespace Drupal\schema_recipe\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNutritionInformationBase;

/**
 * Provides a plugin for the 'nutrition' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_recipe_nutrition",
 *   label = @Translation("nutrition"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. The nutrition for this item."),
 *   name = "nutrition",
 *   group = "schema_recipe",
 *   weight = 8,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaRecipeNutrition extends SchemaNutritionInformationBase {

}
