<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The Android mobile group.
 */
#[MetatagGroup(
  id: 'android_mobile',
  label: new TranslatableMarkup('Android'),
  description: new TranslatableMarkup('Custom meta tags used by the Android OS, browser, etc.'),
  weight: 82,
)]
class AndroidMobile extends GroupBase {
  // Inherits everything from Base.
}
