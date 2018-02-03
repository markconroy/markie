<?php

namespace Drupal\schema_article\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'Article' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_article",
 *   label = @Translation("Schema.org: Article"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/Article"}),
 *   weight = 10,
 * )
 */
class SchemaArticle extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
