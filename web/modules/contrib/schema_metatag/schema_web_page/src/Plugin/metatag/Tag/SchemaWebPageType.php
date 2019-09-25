<?php

namespace Drupal\schema_web_page\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

/**
 * Provides a plugin for the 'schema_web_page_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_web_page_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The type of web page."),
 *   name = "@type",
 *   group = "schema_web_page",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
      'WebPage',
      'ItemPage',
      'AboutPage',
      'CheckoutPage',
      'ContactPage',
      'CollectionPage',
      '- ImageGallery',
      '- VideoGallery',
      'ProfilePage',
      'SearchResultsPage',
    ];
  }

}
