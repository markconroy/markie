<?php

namespace Drupal\metatag_google_cse\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'thumbnail' meta tag.
 */
#[MetatagTag(
  id: 'thumbnail',
  label: new TranslatableMarkup('Thumbnail'),
  name: 'thumbnail',
  group: 'google_cse',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Use a url of a valid image.'),
  weight: 0,
)]
class Thumbnail extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
