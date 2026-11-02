<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Android app Activity Class meta tag.
 */
#[MetatagTag(
  id: 'al_android_class',
  label: new TranslatableMarkup('Android app Activity Class'),
  name: 'al:android:class',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('A fully-qualified Activity class name for intent generation.'),
  weight: 1,
)]
class AlAndroidClass extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
