<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "educationLevel" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_education_level',
  label: new TranslatableMarkup('Education Level'),
  name: 'dcterms.educationLevel',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A class of entity, defined in terms of progression through an educational or training context, for which the described resource is intended.'),
  weight: 6,
)]
class EducationLevel extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
