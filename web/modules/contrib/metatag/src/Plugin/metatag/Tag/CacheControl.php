<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Cache Control meta tag.
 */
#[MetatagTag(
  id: 'cache_control',
  label: new TranslatableMarkup('Cache control'),
  name: 'cache-control',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Used to control whether a browser caches a specific page locally. Not commonly used. Should be used in conjunction with the Pragma meta tag.'),
  weight: 10,
)]
class CacheControl extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
