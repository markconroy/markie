<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks iPhone app name meta tag.
 */
#[MetatagTag(
  id: 'al_iphone_app_name',
  label: new TranslatableMarkup('iPhone app name'),
  name: 'al:iphone:app_name',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The name of the app (suitable for display).'),
  weight: 1,
)]
class AlIphoneAppName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
