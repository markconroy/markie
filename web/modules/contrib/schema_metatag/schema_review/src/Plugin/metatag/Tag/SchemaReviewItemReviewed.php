<?php

namespace Drupal\schema_review\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaThingBase;

/**
 * Provides a plugin for the 'schema_review_item_reviewed' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_review_item_reviewed",
 *   label = @Translation("itemReviewed"),
 *   description = @Translation("The item reviewed."),
 *   name = "itemReviewed",
 *   group = "schema_review",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaReviewItemReviewed extends SchemaThingBase {

}
