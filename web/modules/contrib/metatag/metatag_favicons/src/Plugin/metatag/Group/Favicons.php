<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'Favicons & touch icons' meta tag group.
 */
#[MetatagGroup(
  id: 'favicons',
  label: new TranslatableMarkup('Favicons & touch icons'),
  description: new TranslatableMarkup('Meta tags for displaying favicons of various sizes and types. All values should be either absolute or relative URLs. No effects are added to the "precomposed" icons.'),
  weight: 0,
)]
class Favicons extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
