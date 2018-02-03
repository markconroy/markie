<?php

namespace Drupal\schema_recipe\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'Recipe' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_recipe",
 *   label = @Translation("Schema.org: Recipe"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/Recipe"}),
 *   weight = 10,
 * )
 */
class SchemaRecipe extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
