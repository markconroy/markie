<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_144x144" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_144x144',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 144px x 144px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 144px wide by 144px high. Used with iPad with @2x display running iOS <= 6.'),
  weight: 20,
)]
class AppleTouchIconPrecomposed144x144 extends AppleTouchIcon144x144 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
