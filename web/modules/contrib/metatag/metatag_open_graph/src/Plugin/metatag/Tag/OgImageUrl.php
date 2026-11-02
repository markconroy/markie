<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:image:url' meta tag.
 */
#[MetatagTag(
  id: 'og_image_url',
  label: new TranslatableMarkup('Image URL'),
  name: 'og:image:url',
  group: 'open_graph',
  type: MetatagType::Image,
  description: new TranslatableMarkup('An alternative version of og:image and has exactly the same requirements; only one needs to be used.'),
  weight: 10,
  multiple: TRUE,
  absolute_url: TRUE,
)]
class OgImageUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
