<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Android app package ID meta tag.
 */
#[MetatagTag(
  id: 'al_android_package',
  label: new TranslatableMarkup('Android app package ID'),
  name: 'al:android:package',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('A fully-qualified package name for intent generation. <strong>This attribute is required by the App Links specification.</strong>'),
  weight: 1,
)]
class AlAndroidPackage extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
