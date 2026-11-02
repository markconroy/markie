<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Pragma meta tag.
 */
#[MetatagTag(
  id: 'pragma',
  label: new TranslatableMarkup('Pragma'),
  name: 'pragma',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Used to control whether a browser caches a specific page locally. Not commonly used. Should be used in conjunction with the Cache-Control meta tag.'),
  weight: 12,
)]
class Pragma extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
