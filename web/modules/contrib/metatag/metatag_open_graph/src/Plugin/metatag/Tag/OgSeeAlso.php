<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:see_also' meta tag.
 */
#[MetatagTag(
  id: 'og_see_also',
  label: new TranslatableMarkup('See also'),
  name: 'og:see_also',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('URLs to related content.'),
  weight: 16,
  multiple: TRUE,
)]
class OgSeeAlso extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
