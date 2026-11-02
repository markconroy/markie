<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "hasPart" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_has_part',
  label: new TranslatableMarkup('Has Part'),
  name: 'dcterms.hasPart',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that is included either physically or logically in the described resource.'),
  weight: 6,
)]
class HasPart extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
