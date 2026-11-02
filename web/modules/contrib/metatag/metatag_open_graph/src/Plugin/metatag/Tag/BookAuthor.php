<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Book author" meta tag.
 */
#[MetatagTag(
  id: 'book_author',
  label: new TranslatableMarkup('Book author'),
  name: 'book:author',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup("Links a book to an author's Facebook profile, should be either URLs to the author's profile page or their Facebook profile IDs."),
  weight: 35,
  multiple: TRUE,
)]
class BookAuthor extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
