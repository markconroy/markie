<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:country_name' meta tag.
 */
#[MetatagTag(
  id: 'og_country_name',
  label: new TranslatableMarkup('Country name'),
  name: 'og:country_name',
  group: 'open_graph',
  type: MetatagType::Label,
  weight: 22,
)]
class OgCountryName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
