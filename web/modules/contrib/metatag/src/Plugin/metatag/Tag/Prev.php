<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'prev' meta tag.
 */
#[MetatagTag(
  id: 'prev',
  label: new TranslatableMarkup('Previous page URL'),
  name: 'prev',
  group: 'advanced',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Used for paginated content by providing URL with rel="prev" link.'),
  weight: 2,
)]
class Prev extends LinkRelBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
