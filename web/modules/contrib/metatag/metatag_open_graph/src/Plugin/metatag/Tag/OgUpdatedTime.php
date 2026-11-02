<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:updated_time' meta tag.
 */
#[MetatagTag(
  id: 'og_updated_time',
  label: new TranslatableMarkup('Content modification date & time'),
  name: 'og:updated_time',
  group: 'open_graph',
  type: MetatagType::Date,
  description: new TranslatableMarkup(
    "The date this content was last modified, with an optional time value. Needs to be in <a href=\":url\">ISO 8601</a> format. Can be the same as the 'Article modification date' tag.",
    [':url' => 'https://en.wikipedia.org/wiki/ISO_8601'],
  ),
  weight: 15,
)]
class OgUpdatedTime extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
