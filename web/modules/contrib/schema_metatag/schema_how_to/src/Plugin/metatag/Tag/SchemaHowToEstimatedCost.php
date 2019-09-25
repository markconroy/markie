<?php

namespace Drupal\schema_how_to\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaMonetaryAmountBase;

/**
 * Provides a plugin for the 'schema_how_to_estimated_cost' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_how_to_estimated_cost",
 *   label = @Translation("image"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. The estimated cost of the supplies consumed when performing instructions."),
 *   name = "estimatedCost",
 *   group = "schema_how_to",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaHowToEstimatedCost extends SchemaMonetaryAmountBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
