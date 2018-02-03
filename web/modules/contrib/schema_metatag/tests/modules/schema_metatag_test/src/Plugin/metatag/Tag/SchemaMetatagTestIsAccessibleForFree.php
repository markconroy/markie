<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaIsAccessibleForFreeBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_is_accessible_for_free",
 *   label = @Translation("Schema Metatag Test isAccessibleForFree"),
 *   name = "isAccessibleForFree",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestIsAccessibleForFree extends SchemaIsAccessibleForFreeBase {
}
