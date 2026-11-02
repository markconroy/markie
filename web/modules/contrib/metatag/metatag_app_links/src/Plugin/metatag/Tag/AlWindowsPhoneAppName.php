<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Windows Phone app name meta tag.
 */
#[MetatagTag(
  id: 'al_windows_phone_app_name',
  label: new TranslatableMarkup('Windows Phone app name'),
  name: 'al:windows_phone:app_name',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The name of the app (suitable for display).'),
  weight: 1,
)]
class AlWindowsPhoneAppName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
