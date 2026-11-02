<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:video:height' meta tag.
 */
#[MetatagTag(
  id: 'og_video_height',
  label: new TranslatableMarkup('Video height'),
  name: 'og:video:height',
  group: 'open_graph',
  type: MetatagType::Integer,
  description: new TranslatableMarkup('The height of the above videos(s). Note: if both the unsecured and secured videos are provided, they should both be the same size.'),
  weight: 14,
)]
class OgVideoHeight extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
