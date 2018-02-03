<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_person_given_name' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_given_name",
 *   label = @Translation("givenName"),
 *   description = @Translation("Given name. In the U.S., the first name of a Person. This can be used along with familyName instead of the name property."),
 *   name = "givenName",
 *   group = "schema_person",
 *   weight = -40,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonGivenName extends SchemaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
