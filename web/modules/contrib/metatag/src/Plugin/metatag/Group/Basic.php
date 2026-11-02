<?php

namespace Drupal\metatag\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;

/**
 * The basic group.
 */
#[MetatagGroup(
  id: 'basic',
  label: new TranslatableMarkup('Basic tags'),
  description: new TranslatableMarkup('Simple meta tags.'),
  weight: -10,
)]
class Basic extends GroupBase {
  // Inherits everything from Base.
}
