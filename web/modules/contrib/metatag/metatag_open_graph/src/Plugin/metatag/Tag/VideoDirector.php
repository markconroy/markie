<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'video:director' meta tag.
 */
#[MetatagTag(
  id: 'video_director',
  label: new TranslatableMarkup('Director(s)'),
  name: 'video:director',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Links to the Facebook profiles for director(s) that worked on the video.'),
  weight: 47,
  multiple: TRUE,
)]
class VideoDirector extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
