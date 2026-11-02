<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "extent" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_extent',
  label: new TranslatableMarkup('Extent'),
  name: 'dcterms.extent',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The size or duration of the resource.'),
  weight: 6,
)]
class Extent extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
