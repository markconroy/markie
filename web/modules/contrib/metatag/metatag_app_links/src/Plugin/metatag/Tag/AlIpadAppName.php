<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks iPad app name meta tag.
 */
#[MetatagTag(
  id: 'al_ipad_app_name',
  label: new TranslatableMarkup('iPad app name'),
  name: 'al:ipad:app_name',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The name of the app (suitable for display).'),
  weight: 1,
)]
class AlIpadAppName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
