<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'video:actor' meta tag.
 */
#[MetatagTag(
  id: 'video_actor',
  label: new TranslatableMarkup('Actor(s)'),
  name: 'video:actor',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Links to the Facebook profiles for actor(s) that appear in the video.'),
  weight: 45,
  multiple: TRUE,
)]
class VideoActor extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
