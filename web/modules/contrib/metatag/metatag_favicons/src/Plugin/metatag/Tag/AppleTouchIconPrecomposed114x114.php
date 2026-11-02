<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_114x114" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_114x114',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 114px x 114px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 114px wide by 114px high. Used with iPhone with @2x display running iOS <= 6.'),
  weight: 18,
)]
class AppleTouchIconPrecomposed114x114 extends AppleTouchIcon114x114 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
