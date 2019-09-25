<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaAnswerBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_answer",
 *   label = @Translation("Schema Metatag Test Answer"),
 *   name = "answer",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestAnswer extends SchemaAnswerBase {
}
