<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaRatingBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_aggregate_rating",
 *   label = @Translation("Schema Metatag Test aggregateRating"),
 *   name = "aggregateRating",
 *   description = @Translation("Test aggregateRating"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestAggregateRating extends SchemaRatingBase {
}
