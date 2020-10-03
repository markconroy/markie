<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaGovernmentServiceBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_government_service",
 *   label = @Translation("Schema Metatag Test Government Service"),
 *   name = "governmentService",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMetatagTestGovermentService extends SchemaGovernmentServiceBase {
}
