<?php

namespace Drupal\schema_recipe\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaAggregateRatingBase;

/**
 * Provides a plugin for the 'schema_recipe_aggregate_rating' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_recipe_aggregate_rating",
 *   label = @Translation("aggregateRating"),
 *   description = @Translation("AggregateRating (the numeric AggregateRating of the recipe)."),
 *   name = "aggregateRating",
 *   group = "schema_recipe",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaRecipeAggregateRating extends SchemaAggregateRatingBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '';
    return $form;
  }

}
