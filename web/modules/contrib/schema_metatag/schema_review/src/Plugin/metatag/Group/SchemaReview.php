<?php

namespace Drupal\schema_review\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'Review' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_review",
 *   label = @Translation("Schema.org: Review"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "https://schema.org/Review"}),
 *   weight = 10,
 * )
 */
class SchemaReview extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
