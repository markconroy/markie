<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaProgramMembershipBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_member_of",
 *   label = @Translation("Schema Metatag Test memberOf"),
 *   name = "memberOf",
 *   description = @Translation("Test memberOf"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestMemberOf extends SchemaProgramMembershipBase {
}
