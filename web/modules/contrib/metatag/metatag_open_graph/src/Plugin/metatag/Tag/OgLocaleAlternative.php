<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:locale:alternate' meta tag.
 */
#[MetatagTag(
  id: 'og_locale_alternative',
  label: new TranslatableMarkup('Alternative locales'),
  name: 'og:locale:alternate',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("Other locales this content is available in. Must be in the format language_TERRITORY, e.g. 'fr_FR'."),
  weight: 27,
  multiple: TRUE,
)]
class OgLocaleAlternative extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
