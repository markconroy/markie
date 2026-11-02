<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Article publisher" meta tag.
 */
#[MetatagTag(
  id: 'article_publisher',
  label: new TranslatableMarkup('Article publisher'),
  name: 'article:publisher',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup("Links an article to a publisher's Facebook page."),
  weight: 29,
)]
class ArticlePublisher extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
