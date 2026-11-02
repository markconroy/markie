<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:image' meta tag.
 */
#[MetatagTag(
  id: 'og_image',
  label: new TranslatableMarkup('Image'),
  name: 'og:image',
  group: 'open_graph',
  type: MetatagType::Image,
  description: new TranslatableMarkup('The URL of an image which should represent the content. Should be a minimum of 600px wide, though 1080px is recommended, with square images giving the best results. Supports PNG, JPEG and GIF formats (no SVG!). Should not be used if og:image:url is used. Note: if multiple images are added many services (e.g. Facebook) will default to the largest image, not specifically the first one.'),
  weight: 9,
  multiple: TRUE,
  absolute_url: TRUE,
)]
class OgImage extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
