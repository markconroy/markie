<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'next' meta tag.
 */
#[MetatagTag(
  id: 'next',
  label: new TranslatableMarkup('Next page URL'),
  name: 'next',
  group: 'advanced',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Used for paginated content by providing URL with rel="next" link.'),
  weight: 2,
)]
class Next extends LinkRelBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
