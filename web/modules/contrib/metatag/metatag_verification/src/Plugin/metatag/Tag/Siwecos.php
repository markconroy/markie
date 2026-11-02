<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'siwecostoken' meta tag.
 */
#[MetatagTag(
  id: 'siwecos',
  label: new TranslatableMarkup('SIWECOS'),
  name: 'siwecostoken',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    'A string provided by <a href=":url">SIWECOS</a>, the free website security scanner.',
    [':url' => 'https://siwecos.de/'],
  ),
  weight: 7,
)]
class Siwecos extends MetaNameBase {
}
