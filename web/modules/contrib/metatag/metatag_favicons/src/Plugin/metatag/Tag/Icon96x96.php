<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "icon_96x96" meta tag.
 */
#[MetatagTag(
  id: 'icon_96x96',
  label: new TranslatableMarkup('Icon: 96px x 96px'),
  name: 'icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 96px wide by 96px high.'),
  weight: 5,
)]
class Icon96x96 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '96x96';
  }

}
