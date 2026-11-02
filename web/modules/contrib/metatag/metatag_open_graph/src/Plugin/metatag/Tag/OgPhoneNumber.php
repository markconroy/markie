<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:phone_number' meta tag.
 */
#[MetatagTag(
  id: 'og_phone_number',
  label: new TranslatableMarkup('Phone number'),
  name: 'og:phone_number',
  group: 'open_graph',
  type: MetatagType::String,
  weight: 24,
)]
class OgPhoneNumber extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
