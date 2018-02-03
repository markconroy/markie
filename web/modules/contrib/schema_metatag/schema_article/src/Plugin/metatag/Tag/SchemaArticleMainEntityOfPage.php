<?php

namespace Drupal\schema_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaMainEntityOfPageBase;

/**
 * Provides a plugin for the 'schema_article_main_entity_of_page' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_article_main_entity_of_page",
 *   label = @Translation("mainEntityOfPage"),
 *   description = @Translation(""),
 *   name = "mainEntityOfPage",
 *   group = "schema_article",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaArticleMainEntityOfPage extends SchemaMainEntityOfPageBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
