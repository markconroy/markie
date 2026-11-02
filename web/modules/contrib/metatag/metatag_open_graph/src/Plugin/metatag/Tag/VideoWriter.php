<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'video:writer' meta tag.
 */
#[MetatagTag(
  id: 'video_writer',
  label: new TranslatableMarkup('Writer(s)'),
  name: 'video:writer',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Links to the Facebook profiles for scriptwriter(s) for the video.'),
  weight: 50,
  multiple: TRUE,
)]
class VideoWriter extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
