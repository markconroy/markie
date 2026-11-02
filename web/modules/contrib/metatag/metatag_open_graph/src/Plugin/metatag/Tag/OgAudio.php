<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:audio' meta tag.
 */
#[MetatagTag(
  id: 'og_audio',
  label: new TranslatableMarkup('Audio URL'),
  name: 'og:audio',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('The URL to an audio file that complements this object.'),
  weight: 39,
)]
class OgAudio extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
