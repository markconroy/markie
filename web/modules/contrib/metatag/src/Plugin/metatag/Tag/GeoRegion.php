<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'geo.region' meta tag.
 */
#[MetatagTag(
  id: 'geo_region',
  label: new TranslatableMarkup('Geographical region'),
  name: 'geo.region',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup("A location's two-letter international country code, with an optional two-letter region, e.g. 'US-NH' for New Hampshire in the USA."),
  weight: 0,
)]
class GeoRegion extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
