<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'book:isbn' meta tag.
 */
#[MetatagTag(
  id: 'book_isbn',
  label: new TranslatableMarkup('ISBN'),
  name: 'book:isbn',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("The Book's ISBN"),
  weight: 36,
)]
class BookISBN extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
