<?php

namespace Drupal\schema_video_object\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'VideoObject' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_video_object",
 *   label = @Translation("Schema.org: VideoObject"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/VideoObject"}),
 *   weight = 10,
 * )
 */
class SchemaVideoObject extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
