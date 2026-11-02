<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The Windows mobile group.
 */
#[MetatagGroup(
  id: 'windows_mobile',
  label: new TranslatableMarkup('Windows & Windows Mobile'),
  description: new TranslatableMarkup('Custom meta tags used by the Windows and Windows Mobile OSes, IE browser, etc.'),
  weight: 83,
)]
class WindowsMobile extends GroupBase {
  // Inherits everything from Base.
}
