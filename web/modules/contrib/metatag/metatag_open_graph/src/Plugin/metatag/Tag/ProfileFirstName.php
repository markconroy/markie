<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'profile:first_name' meta tag.
 */
#[MetatagTag(
  id: 'profile_first_name',
  label: new TranslatableMarkup('First name'),
  name: 'profile:first_name',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("The persons's first name."),
  weight: 42,
)]
class ProfileFirstName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
