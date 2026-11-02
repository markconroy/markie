<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_180x180" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_180x180',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 180px x 180px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 180px wide by 180px high. Used with iPhone 6 Plus with @3x display.'),
  weight: 22,
)]
class AppleTouchIconPrecomposed180x180 extends AppleTouchIcon180x180 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
