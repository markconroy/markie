<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'book:release_date' meta tag.
 */
#[MetatagTag(
  id: 'book_release_date',
  label: new TranslatableMarkup('Release Date'),
  name: 'book:release_date',
  group: 'open_graph',
  type: MetatagType::Date,
  description: new TranslatableMarkup(
    'The date the book was released. Needs to be in <a href=":url">ISO 8601</a> format.',
    [':url' => 'https://en.wikipedia.org/wiki/ISO_8601'],
  ),
  weight: 37,
)]
class BookReleaseDate extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
