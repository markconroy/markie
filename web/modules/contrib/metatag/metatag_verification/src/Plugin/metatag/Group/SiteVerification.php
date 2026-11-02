<?php

namespace Drupal\metatag_verification\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The Site Verification group.
 */
#[MetatagGroup(
  id: 'site_verification',
  label: new TranslatableMarkup('Site verification'),
  description: new TranslatableMarkup('These meta tags are used to confirm site ownership for search engines and other services.'),
  weight: 10,
)]
class SiteVerification extends GroupBase {
  // Inherits everything from Base.
}
