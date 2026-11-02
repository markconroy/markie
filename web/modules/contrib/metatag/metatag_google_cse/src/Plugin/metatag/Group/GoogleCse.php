<?php

namespace Drupal\metatag_google_cse\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'Google Custom Search Engine (CSE)' meta tag group.
 */
#[MetatagGroup(
  id: 'google_cse',
  label: new TranslatableMarkup('Google Custom Search Engine (CSE)'),
  description: new TranslatableMarkup('Meta tags used to control the mobile browser experience. Some of these meta tags have been replaced by newer mobile browsers. These meta tags usually only need to be set globally, rather than per-page.'),
  weight: 80,
)]
class GoogleCse extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
