<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Windows Universal app URL scheme meta tag.
 */
#[MetatagTag(
  id: 'al_windows_universal_url',
  label: new TranslatableMarkup('Windows Universal app URL scheme'),
  name: 'al:windows_universal:url',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('A custom scheme for the Windows Universal app. <strong>This attribute is required by the app Links specification.</strong>'),
  weight: 1,
)]
class AlWindowsUniversalUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
