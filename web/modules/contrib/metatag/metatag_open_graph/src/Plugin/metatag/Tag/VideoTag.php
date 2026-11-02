<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'video:tag' meta tag.
 */
#[MetatagTag(
  id: 'video_tag',
  label: new TranslatableMarkup('Tag words'),
  name: 'video:tag',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('Tag words associated with this video.'),
  weight: 49,
  multiple: TRUE,
)]
class VideoTag extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
