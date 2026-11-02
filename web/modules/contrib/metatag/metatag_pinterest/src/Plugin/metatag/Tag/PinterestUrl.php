<?php

namespace Drupal\metatag_pinterest\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'pin:url' meta tag.
 */
#[MetatagTag(
  id: 'pinterest_url',
  label: new TranslatableMarkup('URL'),
  name: 'pin:url',
  group: 'pinterest',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('The URL which should represent the content.'),
  weight: 7,
)]
class PinterestUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
