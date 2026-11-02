<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Web App title for Apple mobile metatag.
 */
#[MetatagTag(
  id: 'apple_mobile_web_app_title',
  label: new TranslatableMarkup('Apple Web App Title'),
  name: 'apple-mobile-web-app-title',
  group: 'apple_mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup('Overrides the long site title when using the Apple Add to Home Screen.'),
  weight: 89,
)]
class AppleMobileWebAppTitle extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
