<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 57px x 57px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 57px wide by 57px high. Used with the non-Retina iPhone, iPod Touch, and Android 2.1+ devices."'),
  weight: 15,
)]
class AppleTouchIconPrecomposed extends AppleTouchIcon {
  // Nothing here yet. Just a placeholder class for a plugin.
}
