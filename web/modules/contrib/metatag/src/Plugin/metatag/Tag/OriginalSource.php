<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The advanced "Original Source" meta tag.
 */
#[MetatagTag(
  id: 'original_source',
  label: new TranslatableMarkup('Original source'),
  name: 'original-source',
  group: 'advanced',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Used to indicate the URL that broke the story, and can link to either an internal URL or an external source. If the full URL is not known it is acceptable to use a partial URL or just the domain name.'),
  weight: 4,
)]
class OriginalSource extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
