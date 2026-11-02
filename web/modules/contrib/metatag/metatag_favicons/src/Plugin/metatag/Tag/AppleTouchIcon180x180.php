<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_180x180" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_180x180',
  label: new TranslatableMarkup('Apple touch icon: 180px x 180px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 180px wide by 180px high. Used with iPhone 6 Plus with @3x display."'),
  weight: 14,
)]
class AppleTouchIcon180x180 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '180x180';
  }

}
