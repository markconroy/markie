<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'geo.placename' meta tag.
 */
#[MetatagTag(
  id: 'geo_placename',
  label: new TranslatableMarkup('Geographical place name'),
  name: 'geo.placename',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup("A location's formal name."),
  weight: 0,
)]
class GeoPlacename extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
