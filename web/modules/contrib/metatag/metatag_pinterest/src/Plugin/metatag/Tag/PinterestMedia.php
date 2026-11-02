<?php

namespace Drupal\metatag_pinterest\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'pin:media' meta tag.
 */
#[MetatagTag(
  id: 'pinterest_media',
  label: new TranslatableMarkup('Media'),
  name: 'pin:media',
  group: 'pinterest',
  type: MetatagType::Image,
  description: new TranslatableMarkup('The URL of media which should represent the content.'),
  weight: 6,
)]
class PinterestMedia extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
