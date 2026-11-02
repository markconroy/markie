<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'canonical' meta tag.
 */
#[MetatagTag(
  id: 'canonical_url',
  label: new TranslatableMarkup('Canonical URL'),
  name: 'canonical',
  group: 'advanced',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('A link to the preferred page location or URL of the content of this page, to help eliminate duplicate content penalties from search engines.'),
  weight: 1,
)]
class CanonicalUrl extends LinkRelBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
