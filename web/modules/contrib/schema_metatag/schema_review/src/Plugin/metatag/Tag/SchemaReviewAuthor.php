<?php

namespace Drupal\schema_review\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'author' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_review_author",
 *   label = @Translation("author"),
 *   description = @Translation("The author of this item."),
 *   name = "author",
 *   group = "schema_review",
 *   weight = 2,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaReviewAuthor extends SchemaPersonOrgBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
