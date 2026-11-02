<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_16x16" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_72x72',
  label: new TranslatableMarkup('Apple touch icon: 72px x 72px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 72px wide by 72px high. Used with the iPad mini and the first- and second-generation iPad (@1x display) on iOS <= 6.'),
  weight: 8,
)]
class AppleTouchIcon72x72 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '72x72';
  }

}
