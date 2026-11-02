<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_114x114" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_114x114',
  label: new TranslatableMarkup('Apple touch icon: 114px x 114px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 114px wide by 114px high. Used with iPhone with @2x display running iOS <= 6.'),
  weight: 10,
)]
class AppleTouchIcon114x114 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '114x114';
  }

}
