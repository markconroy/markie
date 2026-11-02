<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'profile:gender' meta tag.
 */
#[MetatagTag(
  id: 'profile_gender',
  label: new TranslatableMarkup('Gender'),
  name: 'profile:gender',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("Any of Facebook's gender values should be allowed, the initial two being 'male' and 'female'."),
  weight: 44,
)]
class ProfileGender extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
