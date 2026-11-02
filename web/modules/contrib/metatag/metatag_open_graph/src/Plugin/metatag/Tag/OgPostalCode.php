<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:postal_code' meta tag.
 */
#[MetatagTag(
  id: 'og_postal_code',
  label: new TranslatableMarkup('Postal/ZIP code'),
  name: 'og:postal_code',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('The postal code of the location to which the content is relevant.'),
  weight: 21,
)]
class OgPostalCode extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
