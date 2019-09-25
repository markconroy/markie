<?php

namespace Drupal\schema_how_to\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_how_to_name' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_how_to_name",
 *   label = @Translation("name"),
 *   description = @Translation("REQUIRED BY GOOGLE. The title of the how-to. For example, 'How to tie a tie'."),
 *   name = "name",
 *   group = "schema_how_to",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaHowToName extends SchemaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
