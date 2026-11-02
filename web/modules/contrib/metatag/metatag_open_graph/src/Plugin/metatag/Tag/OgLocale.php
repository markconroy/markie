<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:locale' meta tag.
 */
#[MetatagTag(
  id: 'og_locale',
  label: new TranslatableMarkup('Locale'),
  name: 'og:locale',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("The locale these tags are marked up in. Must be in the format language_TERRITORY. Default is 'en_US'."),
  weight: 26,
)]
class OgLocale extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
