<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "icon_32x32" meta tag.
 */
#[MetatagTag(
  id: 'icon_32x32',
  label: new TranslatableMarkup('Icon: 32px x 32px'),
  name: 'icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 32px wide by 32px high.'),
  weight: 4,
)]
class Icon32x32 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '32x32';
  }

}
