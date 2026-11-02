<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "replaces" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_replaces',
  label: new TranslatableMarkup('Replaces'),
  name: 'dcterms.replaces',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that is supplanted, displaced, or superseded by the described resource.'),
  weight: 6,
)]
class Replaces extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
