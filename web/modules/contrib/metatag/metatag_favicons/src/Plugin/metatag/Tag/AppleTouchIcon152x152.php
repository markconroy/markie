<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_152x152" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_152x152',
  label: new TranslatableMarkup('Apple touch icon: 152px x 152px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 152px wide by 152px high. Used with iPad with @2x display running iOS >= 7.'),
  weight: 13,
)]
class AppleTouchIcon152x152 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '152x152';
  }

}
