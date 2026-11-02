<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:longitude' meta tag.
 */
#[MetatagTag(
  id: 'og_longitude',
  label: new TranslatableMarkup('Longitude'),
  name: 'place:location:longitude',
  group: 'open_graph',
  type: MetatagType::String,
  weight: 16,
)]
class OgLongitude extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
