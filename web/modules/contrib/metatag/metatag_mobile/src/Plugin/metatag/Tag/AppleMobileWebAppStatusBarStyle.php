<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Web App status bar style for Apple mobile metatag.
 */
#[MetatagTag(
  id: 'apple_mobile_web_app_status_bar_style',
  label: new TranslatableMarkup('Status bar color'),
  name: 'apple-mobile-web-app-status-bar-style',
  group: 'apple_mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup("Requires the 'Web app capable' meta tag to be set to 'yes'. May be set to 'default', 'black', or 'black-translucent'."),
  weight: 88,
)]
class AppleMobileWebAppStatusBarStyle extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
