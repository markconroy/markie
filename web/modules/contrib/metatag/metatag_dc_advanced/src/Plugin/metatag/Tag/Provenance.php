<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "provenance" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_provenance',
  label: new TranslatableMarkup('Provenance'),
  name: 'dcterms.provenance',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A statement of any changes in ownership and custody of the resource since its creation that are significant for its authenticity, integrity, and interpretation. The statement may include a description of any changes successive custodians made to the resource.'),
  weight: 6,
)]
class Provenance extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
