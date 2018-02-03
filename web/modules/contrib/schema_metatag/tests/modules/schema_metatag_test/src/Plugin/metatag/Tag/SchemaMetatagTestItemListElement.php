<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaItemListElementBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_item_list_element",
 *   label = @Translation("Schema Metatag Test List Element"),
 *   name = "itemListElement",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMetatagTestItemListElement extends SchemaItemListElementBase {

}
