<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The advanced "Image" meta tag.
 */
#[MetatagTag(
  id: 'image_src',
  label: new TranslatableMarkup('Image'),
  name: 'image_src',
  group: 'advanced',
  type: MetatagType::Image,
  description: new TranslatableMarkup('An image associated with this page, for use as a thumbnail in social networks and other services.'),
  weight: 4,
)]
class ImageSrc extends LinkRelBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
