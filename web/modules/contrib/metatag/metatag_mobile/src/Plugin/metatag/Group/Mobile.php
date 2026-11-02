<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'Mobile & UI Adjustments' meta tag group.
 */
#[MetatagGroup(
  id: 'mobile',
  label: new TranslatableMarkup('Mobile & UI Adjustments'),
  description: new TranslatableMarkup('Meta tags used to control the mobile browser experience. Some of these meta tags have been replaced by newer mobile browsers. These meta tags usually only need to be set globally, rather than per-page.'),
  weight: 80,
)]
class Mobile extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
