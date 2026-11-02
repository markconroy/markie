<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'video:series' meta tag.
 */
#[MetatagTag(
  id: 'video_series',
  label: new TranslatableMarkup('Series'),
  name: 'video:series',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('The TV show this series belongs to.'),
  weight: 48,
)]
class VideoSeries extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
