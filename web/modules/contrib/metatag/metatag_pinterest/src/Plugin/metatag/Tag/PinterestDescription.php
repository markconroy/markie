<?php

namespace Drupal\metatag_pinterest\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'pin:description' meta tag.
 */
#[MetatagTag(
  id: 'pinterest_description',
  label: new TranslatableMarkup('Description'),
  name: 'pin:description',
  group: 'pinterest',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A one to two sentence description of the content.'),
  weight: 8,
  trimmable: TRUE,
)]
class PinterestDescription extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
