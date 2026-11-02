<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Android app URL scheme meta tag.
 */
#[MetatagTag(
  id: 'al_android_url',
  label: new TranslatableMarkup('Android app URL scheme'),
  name: 'al:android:url',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('A custom scheme for the Android app.'),
  weight: 1,
)]
class AlAndroidUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
