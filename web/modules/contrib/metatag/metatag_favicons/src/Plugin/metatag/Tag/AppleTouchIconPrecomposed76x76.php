<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_76x76" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_76x76',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 76px x 76px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 76px wide by 76px high. Used with the iPad mini and the second-generation iPad (@1x display) on iOS >= 7.'),
  weight: 17,
)]
class AppleTouchIconPrecomposed76x76 extends AppleTouchIcon76x76 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
