<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "modified" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_modified',
  label: new TranslatableMarkup('Date Modified'),
  name: 'dcterms.modified',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date on which the resource was changed.'),
  weight: 6,
)]
class Modified extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
