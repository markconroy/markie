<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "spatial" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_spatial',
  label: new TranslatableMarkup('Spatial'),
  name: 'dcterms.spatial',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Spatial characteristics of the resource.'),
  weight: 6,
)]
class Spatial extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
