<?php

namespace Drupal\schema_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaDateBase;

/**
 * Provides a plugin for the 'datePublished' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_article_date_published",
 *   label = @Translation("datePublished"),
 *   description = @Translation("REQUIRED BY GOOGLE. Date the article was published."),
 *   name = "datePublished",
 *   group = "schema_article",
 *   weight = 3,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaArticleDatePublished extends SchemaDateBase {

}
