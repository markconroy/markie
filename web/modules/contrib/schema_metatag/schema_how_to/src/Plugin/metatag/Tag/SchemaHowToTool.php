<?php

namespace Drupal\schema_how_to\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_how_to_tool' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_how_to_tool",
 *   label = @Translation("tool"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. An object used (but not consumed) when performing instructions or a direction."),
 *   name = "tool",
 *   group = "schema_how_to",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaHowToTool extends SchemaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
