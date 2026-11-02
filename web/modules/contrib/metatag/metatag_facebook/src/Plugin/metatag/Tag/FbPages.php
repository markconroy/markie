<?php

namespace Drupal\metatag_facebook\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Facebook "fb:pages" meta tag.
 */
#[MetatagTag(
  id: 'fb_pages',
  label: new TranslatableMarkup('Facebook Pages'),
  name: 'fb:pages',
  group: 'facebook',
  type: MetatagType::String,
  description: new TranslatableMarkup('Facebook Instant Articles claim URL token.'),
  weight: 1,
)]
class FbPages extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
