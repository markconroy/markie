<?php

namespace Drupal\schema_image_object\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_image_object_url' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_image_object_url",
 *   label = @Translation("url"),
 *   description = @Translation("Absolute URL of the image. If using tokens include the image preset name, and the URL attribute. [node:field_name:image_preset_name:url]. If using referenced entities like Media or Paragraphs, your token would look like [node:field_name:entity:field_name:image_preset_name:url]."),
 *   name = "url",
 *   group = "schema_image_object",
 *   weight = -45,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaImageObjectUrl extends SchemaNameBase {

}
