<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:image:alt' meta tag.
 */
#[MetatagTag(
  id: 'og_image_alt',
  label: new TranslatableMarkup('Image alternate text'),
  name: 'og:image:alt',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('A description of what is in the image, not a caption. If the page specifies an og:image it should specify og:image:alt.'),
  weight: 15,
)]
class OgImageAlt extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
