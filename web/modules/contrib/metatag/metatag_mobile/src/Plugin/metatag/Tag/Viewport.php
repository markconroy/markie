<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Viewport for Mobile metatag.
 */
#[MetatagTag(
  id: 'viewport',
  label: new TranslatableMarkup('Viewport'),
  name: 'viewport',
  group: 'mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup('Used by most contemporary browsers to control the display for mobile browsers. Please read a guide on responsive web design for details of what values to use.'),
  weight: 84,
)]
class Viewport extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
