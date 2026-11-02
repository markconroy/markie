<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "conformsTo" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_conforms_to',
  label: new TranslatableMarkup('Conforms To'),
  name: 'dcterms.conformsTo',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An established standard to which the described resource conforms.'),
  weight: 6,
)]
class ConformsTo extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
