<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'Dublin Core Additional Tags' meta tag group.
 */
#[MetatagGroup(
  id: 'dublin_core_advanced',
  label: new TranslatableMarkup('Dublin Core Additional Tags'),
  description: new TranslatableMarkup('These tags are not part of the Metadata Element Set but may be useful for certain scenarios.'),
  weight: 4,
)]
class DublinCoreAdvanced extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
