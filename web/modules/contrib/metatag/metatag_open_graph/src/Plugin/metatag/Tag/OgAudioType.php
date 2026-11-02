<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:audio:type' meta tag.
 */
#[MetatagTag(
  id: 'og_audio_type',
  label: new TranslatableMarkup('Audio type'),
  name: 'og:audio:type',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("The MIME type of the audio file. Examples include 'application/mp3' for an MP3 file."),
  weight: 41,
)]
class OgAudioType extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
