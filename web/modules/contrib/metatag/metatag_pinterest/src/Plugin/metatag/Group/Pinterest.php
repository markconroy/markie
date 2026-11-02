<?php

namespace Drupal\metatag_pinterest\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The Pinterest group.
 */
#[MetatagGroup(
  id: 'pinterest',
  label: new TranslatableMarkup('Pinterest'),
  description: new TranslatableMarkup(
    'A set of meta tags used to control how the site\'s content is consumed by <a href=":url">@site_name</a>.',
    [
      ':url' => 'https://pinterest.com/',
      '@site_name' => 'Pinterest',
    ]
  ),
  weight: 4,
)]
class Pinterest extends GroupBase {
  // Inherits everything from Base.
}
