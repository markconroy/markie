<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:street_address' meta tag.
 */
#[MetatagTag(
  id: 'og_street_address',
  label: new TranslatableMarkup('Street address'),
  name: 'og:street_address',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('The street address of the author of the content, or to which the content pertains.'),
  weight: 18,
)]
class OgStreetAddress extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
