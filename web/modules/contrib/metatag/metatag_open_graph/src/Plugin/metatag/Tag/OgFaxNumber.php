<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:fax_number' meta tag.
 */
#[MetatagTag(
  id: 'og_fax_number',
  label: new TranslatableMarkup('Fax number'),
  name: 'og:fax_number',
  group: 'open_graph',
  type: MetatagType::String,
  weight: 25,
)]
class OgFaxNumber extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
