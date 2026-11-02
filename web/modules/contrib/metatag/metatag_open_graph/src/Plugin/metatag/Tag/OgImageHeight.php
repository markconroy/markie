<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:image:height' meta tag.
 */
#[MetatagTag(
  id: 'og_image_height',
  label: new TranslatableMarkup('Image height'),
  name: 'og:image:height',
  group: 'open_graph',
  type: MetatagType::Integer,
  description: new TranslatableMarkup('The height of the above image(s). Note: if both the unsecured and secured images are provided, they should both be the same size.'),
  weight: 14,
)]
class OgImageHeight extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
