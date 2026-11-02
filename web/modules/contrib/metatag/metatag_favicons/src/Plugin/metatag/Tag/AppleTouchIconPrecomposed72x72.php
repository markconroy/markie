<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon-precomposed_72x72" meta tag.
 *
 * This is basically a clone of the non-precomposed class.
 */
#[MetatagTag(
  id: 'apple_touch_icon_precomposed_72x72',
  label: new TranslatableMarkup('Apple touch icon (precomposed): 72px x 72px'),
  name: 'apple-touch-icon-precomposed',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 72px wide by 72px high. Used with the iPad mini and the first- and second-generation iPad (@1x display) on iOS <= 6."'),
  weight: 16,
)]
class AppleTouchIconPrecomposed72x72 extends AppleTouchIcon72x72 {
  // Nothing here yet. Just a placeholder class for a plugin.
}
