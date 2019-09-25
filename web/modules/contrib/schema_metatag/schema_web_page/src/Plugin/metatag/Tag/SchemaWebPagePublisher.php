<?php

namespace Drupal\schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'publisher' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_web_page_publisher",
 *   label = @Translation("publisher"),
 *   description = @Translation("Publisher of the web page."),
 *   name = "publisher",
 *   group = "schema_web_page",
 *   weight = 3,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPagePublisher extends SchemaPersonOrgBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['name']['#attribute']['placeholder'] = '[site:name]';
    $form['url']['#attributes']['placeholder'] = '[site:url]';
    return $form;
  }

}
