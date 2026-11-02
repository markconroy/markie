<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Web App Capable for Apple mobile metatag.
 *
 * @deprecated in metatag:2.2.1 and is removed from metatag:3.0.0. Use mobile_web_app_capable instead.
 *
 * @see https://www.drupal.org/project/metatag/issues/3483034
 * @see https://www.drupal.org/project/metatag/issues/3620079
 */
#[MetatagTag(
  id: 'apple_mobile_web_app_capable',
  label: new TranslatableMarkup('Web app capable? (deprecated)'),
  name: 'apple-mobile-web-app-capable',
  group: 'apple_mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup("If set to 'yes', the application will run in full-screen mode; the default behavior is to use Safari to display web content."),
  weight: 87,
)]
class AppleMobileWebAppCapable extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
