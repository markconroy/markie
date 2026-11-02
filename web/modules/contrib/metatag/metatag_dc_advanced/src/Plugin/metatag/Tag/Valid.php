<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "valid" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_valid',
  label: new TranslatableMarkup('Date Valid'),
  name: 'dcterms.valid',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date (often a range) of validity of a resource.'),
  weight: 6,
)]
class Valid extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
