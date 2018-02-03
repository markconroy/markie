<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_person_name' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_name",
 *   label = @Translation("name"),
 *   description = @Translation("The name of the person."),
 *   name = "name",
 *   group = "schema_person",
 *   weight = -45,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonName extends SchemaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
