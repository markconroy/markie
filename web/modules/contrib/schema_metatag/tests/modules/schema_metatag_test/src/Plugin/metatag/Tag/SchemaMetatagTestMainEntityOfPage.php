<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaMainEntityOfPageBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_main_entity_of_page",
 *   label = @Translation("Schema Metatag Test Main Entity of Page"),
 *   name = "mainEntityOfPage",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestMainEntityOfPage extends SchemaMainEntityOfPageBase {
}
