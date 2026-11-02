<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:video:width' meta tag.
 */
#[MetatagTag(
  id: 'og_video_width',
  label: new TranslatableMarkup('Video width'),
  name: 'og:video:width',
  group: 'open_graph',
  type: MetatagType::Integer,
  description: new TranslatableMarkup('The width of the above video(s). Note: if both the unsecured and secured videos are provided, they should both be the same size.'),
  weight: 13,
)]
class OgVideoWidth extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
