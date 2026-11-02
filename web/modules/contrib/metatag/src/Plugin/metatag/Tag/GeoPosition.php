<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'geo.position' meta tag.
 */
#[MetatagTag(
  id: 'geo_position',
  label: new TranslatableMarkup('Geographical position'),
  name: 'geo.position',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    "Geo-spatial information in 'latitude; longitude' format, e.g. '50.167958; -97.133185'; <a href=\":url\" aria-label=\"see Wikipedia for details on the geographic coordinate system\">see Wikipedia for details</a>.",
    [':url' => 'https://en.wikipedia.org/wiki/Geographic_coordinate_system']
  ),
  weight: 0,
)]
class GeoPosition extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
