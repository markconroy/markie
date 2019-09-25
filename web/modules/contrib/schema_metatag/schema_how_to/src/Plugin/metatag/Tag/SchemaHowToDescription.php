<?php

namespace Drupal\schema_how_to\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_how_to_description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_how_to_description",
 *   label = @Translation("description"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. A description of the how-to."),
 *   name = "description",
 *   group = "schema_how_to",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaHowToDescription extends SchemaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
