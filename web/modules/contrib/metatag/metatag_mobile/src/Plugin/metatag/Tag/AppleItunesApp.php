<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Itunes App for Apple mobile metatag.
 */
#[MetatagTag(
  id: 'apple_itunes_app',
  label: new TranslatableMarkup('iTunes App details'),
  name: 'apple-itunes-app',
  group: 'apple_mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup("This informs iOS devices to display a banner to a specific app. If used, it must provide the 'app-id' value, the 'affiliate-data' and 'app-argument' values are optional."),
  weight: 86,
)]
class AppleItunesApp extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
