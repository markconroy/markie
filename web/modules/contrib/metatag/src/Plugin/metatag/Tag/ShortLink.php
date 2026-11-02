<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'shortlink' meta tag.
 */
#[MetatagTag(
  id: 'shortlink',
  label: new TranslatableMarkup('Shortlink URL'),
  name: 'shortlink',
  group: 'advanced',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('A brief URL, often created by a URL shortening service.'),
  weight: 1,
)]
class ShortLink extends LinkRelBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
