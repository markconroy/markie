<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaEventBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_event",
 *   label = @Translation("Schema Metatag Test Event"),
 *   name = "event",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestEvent extends SchemaEventBase {
}
