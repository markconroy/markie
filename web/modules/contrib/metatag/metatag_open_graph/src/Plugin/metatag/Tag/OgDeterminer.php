<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:determiner' meta tag.
 */
#[MetatagTag(
  id: 'og_determiner',
  label: new TranslatableMarkup('Determiner'),
  name: 'og:determiner',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup("The word that appears before the content's title in a sentence. The default ignores this value, the 'Automatic' value should be sufficient if this is actually needed."),
  weight: 0,
)]
class OgDeterminer extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
