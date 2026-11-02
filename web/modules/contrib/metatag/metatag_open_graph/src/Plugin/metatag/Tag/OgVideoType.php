<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:video:type' meta tag.
 */
#[MetatagTag(
  id: 'og_video_type',
  label: new TranslatableMarkup('Video type'),
  name: 'og:video:type',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("The type of image referenced above. Should be either 'video.episode', 'video.movie', 'video.other', or 'video.tv_show'Note: there should be one value for each video, and having more than there are videos may cause problems."),
  weight: 12,
)]
class OgVideoType extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
