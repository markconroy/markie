<?php

namespace Drupal\metatag_hreflang\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * A plugin for the 'Alternative language links (hreflang)' meta tag group.
 */
#[MetatagGroup(
  id: 'hreflang',
  label: new TranslatableMarkup('Alternative language links (hreflang)'),
  description: new TranslatableMarkup('These meta tags are designed to point visitors to versions of the current page in other languages.'),
  weight: 60,
)]
class Hreflang extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
