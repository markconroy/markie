<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "isReferencedBy" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_is_referenced_by',
  label: new TranslatableMarkup('Is Referenced By'),
  name: 'dcterms.isReferencedBy',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that references, cites, or otherwise points to the described resource.'),
  weight: 6,
)]
class IsReferencedBy extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
