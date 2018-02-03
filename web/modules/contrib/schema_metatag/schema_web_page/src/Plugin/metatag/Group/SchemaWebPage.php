<?php

namespace Drupal\schema_web_page\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'WebPage' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_web_page",
 *   label = @Translation("Schema.org: WebPage"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/WebPage"}),
 *   weight = 10,
 * )
 */
class SchemaWebPage extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
