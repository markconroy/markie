<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'profile:last_name' meta tag.
 */
#[MetatagTag(
  id: 'profile_last_name',
  label: new TranslatableMarkup('Last name'),
  name: 'profile:last_name',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup("The person's last name."),
  weight: 43,
)]
class ProfileLastName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
