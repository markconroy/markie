<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'App Links' meta tag group.
 */
#[MetatagGroup(
  id: 'app_links',
  label: new TranslatableMarkup('App Links'),
  description: new TranslatableMarkup(
    'Meta tags used to expose App Links for app deep linking. See <a href=":url"">applinks.org</a> for details and documentation.',
    [':url' => 'https://applinks.org'],
  ),
  weight: 0,
)]
class AppLinks extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
