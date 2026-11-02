<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_152x152" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_152x152',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 152px x 152px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 152px wide by 152px high. Used with iPad with @2x display running iOS >= 7.'),
  weight: 21,
)]
class AppleTouchIconPrecomposed152x152 extends AppleTouchIcon152x152 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
