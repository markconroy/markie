<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:region' meta tag.
 */
#[MetatagTag(
  id: 'og_region',
  label: new TranslatableMarkup('Region'),
  name: 'og:region',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The region within a country to which the content is relevant.'),
  weight: 20,
)]
class OgRegion extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
