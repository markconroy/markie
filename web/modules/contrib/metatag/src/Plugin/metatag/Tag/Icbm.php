<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'icbm' meta tag.
 */
#[MetatagTag(
  id: 'icbm',
  label: new TranslatableMarkup('ICBM'),
  name: 'icbm',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    "Geo-spatial information in 'latitude, longitude' format, e.g. '50.167958, -97.133185'; <a href=\":url\" aria-label=\"see Wikipedia for details on ICBM addresses\">see Wikipedia for details</a>.",
    [':url' => 'https://en.wikipedia.org/wiki/ICBM_address']
  ),
  weight: 0,
)]
class Icbm extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
