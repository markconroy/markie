<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "medium" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_medium',
  label: new TranslatableMarkup('Medium'),
  name: 'dcterms.medium',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The material or physical carrier of the resource.'),
  weight: 6,
)]
class Medium extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
