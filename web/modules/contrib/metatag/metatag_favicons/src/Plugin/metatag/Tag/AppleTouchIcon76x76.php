<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "apple-touch-icon_76x76" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon_76x76',
  label: new TranslatableMarkup('Apple touch icon: 76px x 76px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 76px wide by 76px high. Used with the iPad mini and the second-generation iPad (@1x display) on iOS >= 7.'),
  weight: 9,
)]
class AppleTouchIcon76x76 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '76x76';
  }

}
