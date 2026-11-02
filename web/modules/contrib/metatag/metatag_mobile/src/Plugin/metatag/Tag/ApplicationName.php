<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'application:name' meta tag.
 */
#[MetatagTag(
  id: 'application_name',
  label: new TranslatableMarkup('Application name'),
  name: 'application-name',
  group: 'windows_mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup('The default name displayed with the pinned sites tile (or icon). Set the content attribute to the desired name.'),
  weight: 94,
)]
class ApplicationName extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
