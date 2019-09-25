<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_person_url' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_url",
 *   label = @Translation("url"),
 *   description = @Translation("REQUIRED BY GOOGLE. The URL for the person's official website."),
 *   name = "url",
 *   group = "schema_person",
 *   weight = -4,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonUrl extends SchemaNameBase {

}
