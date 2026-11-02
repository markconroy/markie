<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Windows Phone app ID meta tag.
 */
#[MetatagTag(
  id: 'al_windows_phone_app_id',
  label: new TranslatableMarkup('Windows Phone app ID'),
  name: 'al:windows_phone:app_id',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The app ID for the app store.'),
  weight: 1,
)]
class AlWindowsPhoneAppId extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
