<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "icon_16x16" meta tag.
 */
#[MetatagTag(
  id: 'icon_16x16',
  label: new TranslatableMarkup('Icon: 16px x 16px'),
  name: 'icon',
  group: 'favicons',
  type:  MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 16px wide by 16px high.'),
  weight: 3,
)]
class Icon16x16 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '16x16';
  }

}
