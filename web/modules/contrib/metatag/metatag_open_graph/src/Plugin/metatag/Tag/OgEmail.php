<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:email' meta tag.
 */
#[MetatagTag(
  id: 'og_email',
  label: new TranslatableMarkup('Email address'),
  name: 'og:email',
  group: 'open_graph',
  type: MetatagType::String,
  weight: 23,
)]
class OgEmail extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
