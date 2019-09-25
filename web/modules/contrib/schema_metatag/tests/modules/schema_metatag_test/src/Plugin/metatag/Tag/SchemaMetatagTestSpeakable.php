<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaSpeakableBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_speakable",
 *   label = @Translation("Schema Metatag Test Speakable"),
 *   name = "speakable",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestSpeakable extends SchemaSpeakableBase {
}
