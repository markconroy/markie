<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "isRequiredBy" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_is_required_by',
  label: new TranslatableMarkup('Is Required By'),
  name: 'dcterms.isRequiredBy',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that requires the described resource to support its function, delivery, or coherence.'),
  weight: 6,
)]
class IsRequiredBy extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
