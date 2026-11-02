<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "temporal" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_temporal',
  label: new TranslatableMarkup('Temporal'),
  name: 'dcterms.temporal',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Temporal characteristics of the resource.'),
  weight: 6,
)]
class Temporal extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
