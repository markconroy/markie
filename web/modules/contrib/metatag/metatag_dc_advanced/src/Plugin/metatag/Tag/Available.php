<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "available" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_available',
  label: new TranslatableMarkup('Date Available'),
  name: 'dcterms.available',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date (often a range) that the resource became or will become available.'),
  weight: 6,
)]
class Available extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
