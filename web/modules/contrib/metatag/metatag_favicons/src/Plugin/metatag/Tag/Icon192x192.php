<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Favicons "icon_192x192" meta tag.
 */
#[MetatagTag(
  id: 'icon_192x192',
  label: new TranslatableMarkup('Icon: 192px x 192px'),
  name: 'icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 192px wide by 192px high.'),
  weight: 6,
)]
class Icon192x192 extends LinkSizesBase {

  /**
   * {@inheritdoc}
   */
  protected function iconSize(): string {
    return '192x192';
  }

}
