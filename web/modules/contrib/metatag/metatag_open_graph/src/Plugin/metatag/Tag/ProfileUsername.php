<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'profile:username' meta tag.
 */
#[MetatagTag(
  id: 'profile_username',
  label: new TranslatableMarkup('Username'),
  name: 'profile:username',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('A pseudonym/alias of this person.'),
  weight: 45,
)]
class ProfileUsername extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
