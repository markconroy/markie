<?php

namespace Drupal\metatag\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;

/**
 * The advanced group.
 */
#[MetatagGroup(
  id: 'advanced',
  label: new TranslatableMarkup('Advanced'),
  description: new TranslatableMarkup('Meta tags that might not be needed by many sites.'),
  weight: -9,
)]
class Advanced extends GroupBase {
  // Inherits everything from Base.
}
