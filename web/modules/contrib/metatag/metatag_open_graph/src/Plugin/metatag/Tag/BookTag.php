<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Book tag" meta tag.
 */
#[MetatagTag(
  id: 'book_tag',
  label: new TranslatableMarkup('Book tag(s)'),
  name: 'book:tag',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Appropriate keywords for this content.'),
  weight: 38,
  multiple: TRUE,
)]
class BookTag extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
