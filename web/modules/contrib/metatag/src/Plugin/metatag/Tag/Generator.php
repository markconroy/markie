<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Generator" meta tag.
 */
#[MetatagTag(
  id: 'generator',
  label: new TranslatableMarkup('Generator'),
  name: 'generator',
  group: 'advanced',
  type: MetatagType::String,
  description: new TranslatableMarkup('Describes the name and version number of the software or publishing tool used to create the page.'),
  weight: 4,
)]
class Generator extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
