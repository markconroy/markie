<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Format Detection for Apple mobile metatag.
 */
#[MetatagTag(
  id: 'format_detection',
  label: new TranslatableMarkup('Format detection'),
  name: 'format-detection',
  group: 'apple_mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup("If set to 'telephone=no' the page will not be checked for phone numbers, which would be presented."),
  weight: 90,
)]
class FormatDetection extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
