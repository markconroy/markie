<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Type" meta tag.
 */
#[MetatagTag(
  id: 'og_type',
  label: new TranslatableMarkup('Content type'),
  name: 'og:type',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('The type of the content, e.g., <em>movie</em>.'),
  weight: 2,
)]
class OgType extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
