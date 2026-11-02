<?php

namespace Drupal\metatag_google_cse\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'audience' meta tag.
 */
#[MetatagTag(
  id: 'audience',
  label: new TranslatableMarkup('Content audience'),
  name: 'audience',
  group: 'google_cse',
  type: MetatagType::String,
  description: new TranslatableMarkup('The content audience, e.g. "all".'),
  weight: 0,
)]
class Audience extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
