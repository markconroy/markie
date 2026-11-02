<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Article section" meta tag.
 */
#[MetatagTag(
  id: 'article_section',
  label: new TranslatableMarkup('Article section'),
  name: 'article:section',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The primary section of this website the content belongs to.'),
  weight: 30,
)]
class ArticleSection extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
