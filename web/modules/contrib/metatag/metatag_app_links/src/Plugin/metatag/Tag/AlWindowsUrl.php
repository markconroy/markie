<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Windows App URL scheme meta tag.
 */
#[MetatagTag(
  id: 'al_windows_url',
  label: new TranslatableMarkup('Windows app URL scheme'),
  name: 'al:windows:url',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('A custom scheme for the Windows app. <strong>This attribute is required by the app Links specification.</strong>'),
  weight: 1,
)]
class AlWindowsUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
