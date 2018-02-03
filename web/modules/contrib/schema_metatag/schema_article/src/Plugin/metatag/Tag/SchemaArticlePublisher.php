<?php

namespace Drupal\schema_article\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'publisher' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_article_publisher",
 *   label = @Translation("publisher"),
 *   description = @Translation("Publisher of the article."),
 *   name = "publisher",
 *   group = "schema_article",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaArticlePublisher extends SchemaPersonOrgBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['name']['#attributes']['placeholder'] = '[site:name]';
    $form['url']['#attributes']['placeholder'] = '[site:url]';
    return $form;
  }

}
