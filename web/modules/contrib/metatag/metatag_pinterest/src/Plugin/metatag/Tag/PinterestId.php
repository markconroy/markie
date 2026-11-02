<?php

namespace Drupal\metatag_pinterest\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'pin:id' meta tag.
 */
#[MetatagTag(
  id: 'pinterest_id',
  label: new TranslatableMarkup('Id'),
  name: 'pin:id',
  group: 'pinterest',
  type: MetatagType::String,
  description: new TranslatableMarkup('The Canonical Pinterest object to pin.'),
  weight: 5,
)]
class PinterestId extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
