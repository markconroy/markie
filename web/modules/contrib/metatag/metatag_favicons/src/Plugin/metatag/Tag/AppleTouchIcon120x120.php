<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_120x120" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_120x120',
  label: new TranslatableMarkup('Apple touch icon: 120px x 120px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 120px wide by 120px high. Used with iPhone with @2x display running iOS >= 7.'),
  weight: 11,
)]
class AppleTouchIcon120x120 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '120x120';
  }

}
