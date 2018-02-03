<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaAddressBase;

/**
 * Provides a plugin for the 'schema_person_address' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_address",
 *   label = @Translation("address"),
 *   description = @Translation("Physical address of the person."),
 *   name = "address",
 *   group = "schema_person",
 *   weight = 0,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonAddress extends SchemaAddressBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
