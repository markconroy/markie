<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:image:secure_url' meta tag.
 */
#[MetatagTag(
  id: 'og_image_secure_url',
  label: new TranslatableMarkup('Image Secure URL'),
  name: 'og:image:secure_url',
  group: 'open_graph',
  type: MetatagType::Image,
  description: new TranslatableMarkup('The secure URL (HTTPS) of an image which should represent the content. The image must be at least 200 x 200 pixels in size; 600 x 316 pixels is a recommended minimum size, and for best results use an image least 1200 x 630 pixels in size. Supports PNG, JPEG and GIF formats.'),
  weight: 11,
  secure: TRUE,
  multiple: TRUE,
  absolute_url: TRUE,
)]
class OgImageSecureUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
