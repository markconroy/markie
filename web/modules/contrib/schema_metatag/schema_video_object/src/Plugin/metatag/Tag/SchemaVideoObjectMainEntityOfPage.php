<?php

namespace Drupal\schema_video_object\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaMainEntityOfPageBase;

/**
 * Provides a plugin for the 'schema_video_object_main_entity_of_page' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_video_object_main_entity_of_page",
 *   label = @Translation("mainEntityOfPage"),
 *   description = @Translation(""),
 *   name = "mainEntityOfPage",
 *   group = "schema_video_object",
 *   weight = 0,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaVideoObjectMainEntityOfPage extends SchemaMainEntityOfPageBase {

}
