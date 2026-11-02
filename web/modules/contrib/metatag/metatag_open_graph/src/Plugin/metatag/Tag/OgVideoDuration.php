<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:video:duration' meta tag.
 */
#[MetatagTag(
  id: 'og_video_duration',
  label: new TranslatableMarkup('Video duration (seconds)'),
  name: 'og:video:duration',
  group: 'open_graph',
  type: MetatagType::Integer,
  description: new TranslatableMarkup('The length of the video in seconds.'),
  weight: 15,
)]
class OgVideoDuration extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
