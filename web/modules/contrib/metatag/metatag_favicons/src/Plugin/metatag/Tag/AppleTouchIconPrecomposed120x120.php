<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_120x120" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_120x120',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 120px x 120px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 120px wide by 120px high. Used with iPhone with @2x display running iOS >= 7.'),
  weight: 19,
)]
class AppleTouchIconPrecomposed120x120 extends AppleTouchIcon120x120 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
