<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_144x144" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_144x144',
  label: new TranslatableMarkup('Apple touch icon: 144px x 144px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 144px wide by 144px high. Used with iPad with @2x display running iOS <= 6.'),
  weight: 12,
)]
class AppleTouchIcon144x144 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '144x144';
  }

}
