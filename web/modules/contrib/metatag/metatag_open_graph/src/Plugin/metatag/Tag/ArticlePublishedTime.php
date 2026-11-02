<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Article published time" meta tag.
 */
#[MetatagTag(
  id: 'article_published_time',
  label: new TranslatableMarkup('Article publication date & time'),
  name: 'article:published_time',
  group: 'open_graph',
  // @todo Should this be a date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    'The date this content was published on, with an optional time value. Needs to be in <a href=":url">ISO 8601</a> format.',
    [':url' => 'https://en.wikipedia.org/wiki/ISO_8601'],
  ),
  weight: 32,
)]
class ArticlePublishedTime extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
