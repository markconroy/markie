<?php

namespace Drupal\metatag_google_cse\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'department' meta tag.
 */
#[MetatagTag(
  id: 'department',
  label: new TranslatableMarkup('Department'),
  name: 'department',
  group: 'google_cse',
  type: MetatagType::String,
  description: new TranslatableMarkup('Department tag.'),
  weight: 0,
)]
class Department extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
