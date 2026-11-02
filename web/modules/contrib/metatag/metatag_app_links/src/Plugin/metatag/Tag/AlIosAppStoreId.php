<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks iOS app store ID meta tag.
 */
#[MetatagTag(
  id: 'al_ios_app_store_id',
  label: new TranslatableMarkup('iOS app store ID'),
  name: 'al:ios:app_store_id',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The app ID for the app store.'),
  weight: 1,
)]
class AlIosAppStoreId extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
