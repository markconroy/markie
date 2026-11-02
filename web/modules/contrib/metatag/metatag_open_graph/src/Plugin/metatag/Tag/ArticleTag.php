<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Article tag" meta tag.
 */
#[MetatagTag(
  id: 'article_tag',
  label: new TranslatableMarkup('Article tag(s)'),
  name: 'article:tag',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Appropriate keywords for this content.'),
  weight: 31,
  multiple: TRUE,
)]
class ArticleTag extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
