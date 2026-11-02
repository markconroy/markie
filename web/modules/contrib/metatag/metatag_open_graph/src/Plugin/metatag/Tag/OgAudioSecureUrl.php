<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:audio:secure_url' meta tag.
 */
#[MetatagTag(
  id: 'og_audio_secure_url',
  label: new TranslatableMarkup('Audio secure URL'),
  name: 'og:audio:secure_url',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup("The secure URL to an audio file that complements this object. All 'http://' URLs will automatically be converted to 'https://'."),
  weight: 40,
  secure: TRUE,
)]
class OgAudioSecureUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
