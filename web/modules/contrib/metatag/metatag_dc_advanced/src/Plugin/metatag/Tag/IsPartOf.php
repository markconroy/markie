<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "isPartOf" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_is_part_of',
  label: new TranslatableMarkup('Is Part Of'),
  name: 'dcterms.isPartOf',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource in which the described resource is physically or logically included.'),
  weight: 6,
)]
class IsPartOf extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
