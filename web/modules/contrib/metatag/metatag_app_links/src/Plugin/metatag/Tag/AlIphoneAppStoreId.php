<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks iPad app store ID meta tag.
 */
#[MetatagTag(
  id: 'al_iphone_app_store_id',
  label: new TranslatableMarkup('iPhone app store ID'),
  name: 'al:iphone:app_store_id',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The app ID for the app store.'),
  weight: 1,
)]
class AlIphoneAppStoreId extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
