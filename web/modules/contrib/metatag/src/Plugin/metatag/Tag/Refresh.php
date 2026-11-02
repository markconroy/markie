<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Refresh" meta tag.
 */
#[MetatagTag(
  id: 'refresh',
  label: new TranslatableMarkup('Refresh'),
  name: 'refresh',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The number of seconds to wait before refreshing the page. May also force redirect to another page using the format "5; url=https://example.com/", which would be triggered after five seconds.'),
  weight: 5,
)]
class Refresh extends MetaHttpEquivBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
