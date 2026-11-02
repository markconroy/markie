<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'baidu-site-verification' meta tag.
 */
#[MetatagTag(
  id: 'baidu',
  label: new TranslatableMarkup('Baidu'),
  name: 'baidu-site-verification',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    'A string provided by <a href=":baidu">Baidu</a>',
    [':baidu' => 'https://www.baidu.com/'],
  ),
  weight: 1,
)]
class Baidu extends MetaNameBase {
}
