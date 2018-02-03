<?php

namespace Drupal\schema_recipe\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'recipeInstructions' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_recipe_recipe_instructions",
 *   label = @Translation("recipeInstructions"),
 *   description = @Translation("Steps in making the recipe, in the form of a single item (document, video, etc.) or an ordered list with HowToStep and/or HowToSection items."),
 *   name = "recipeInstructions",
 *   group = "schema_recipe",
 *   weight = 5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaRecipeRecipeInstructions extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[node:field_instruction]';
    return $form;
  }

}
