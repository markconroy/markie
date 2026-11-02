<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Title" meta tag.
 */
#[MetatagTag(
  id: 'og_title',
  label: new TranslatableMarkup('Title'),
  name: 'og:title',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The title of the content, e.g., <em>The Rock</em>.'),
  weight: 4,
  trimmable: TRUE,
)]
class OgTitle extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
