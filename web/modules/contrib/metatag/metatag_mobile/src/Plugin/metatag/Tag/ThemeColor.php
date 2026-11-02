<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Theme color for Mobile metatag.
 */
#[MetatagTag(
  id: 'theme_color',
  label: new TranslatableMarkup('Theme Color'),
  name: 'theme-color',
  group: 'mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup("A color in hexadecimal format, e.g. '#0000ff' for blue; must include the '#' symbol. Used by some browsers to control the background color of the toolbar, the color used with an icon, etc."),
  weight: 81,
)]
class ThemeColor extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
