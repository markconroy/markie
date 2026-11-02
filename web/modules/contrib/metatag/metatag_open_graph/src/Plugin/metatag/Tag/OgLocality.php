<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:locality' meta tag.
 */
#[MetatagTag(
  id: 'og_locality',
  label: new TranslatableMarkup('Locality'),
  name: 'og:locality',
  group: 'open_graph',
  type: MetatagType::Label,
  weight: 19,
)]
class OgLocality extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
