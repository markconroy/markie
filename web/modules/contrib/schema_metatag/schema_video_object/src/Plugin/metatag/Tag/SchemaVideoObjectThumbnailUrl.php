<?php

namespace Drupal\schema_video_object\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_video_object_thumbnail_url' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_video_object_thumbnail_url",
 *   label = @Translation("thumbnailUrl"),
 *   description = @Translation("REQUIRED BY GOOGLE. The thumbnail URL(s) of the video(s)."),
 *   name = "thumbnailUrl",
 *   group = "schema_video_object",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaVideoObjectThumbnailUrl extends SchemaNameBase {

}
