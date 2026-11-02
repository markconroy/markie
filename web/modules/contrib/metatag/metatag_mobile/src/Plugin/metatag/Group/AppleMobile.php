<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The Apple mobile group.
 */
#[MetatagGroup(
  id: 'apple_mobile',
  label: new TranslatableMarkup('Apple & iOS'),
  description: new TranslatableMarkup("Custom meta tags used by Apple's software, iOS, Safari, etc."),
  weight: 81,
)]
class AppleMobile extends GroupBase {
  // Inherits everything from Base.
}
