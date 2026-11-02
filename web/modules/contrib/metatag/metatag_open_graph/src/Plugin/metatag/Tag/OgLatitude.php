<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:latitude' meta tag.
 */
#[MetatagTag(
  id: 'og_latitude',
  label: new TranslatableMarkup('Latitude'),
  name: 'place:location:latitude',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('The latitude of the location relevant to the content.'),
  weight: 16,
)]
class OgLatitude extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
