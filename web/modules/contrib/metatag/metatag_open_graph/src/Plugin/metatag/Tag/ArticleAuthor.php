<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Article author" meta tag.
 */
#[MetatagTag(
  id: 'article_author',
  label: new TranslatableMarkup('Article author'),
  name: 'article:author',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup("Links an article to an author's Facebook profile, should be either URLs to the author's profile page or their Facebook profile IDs."),
  weight: 28,
  multiple: TRUE,
)]
class ArticleAuthor extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
