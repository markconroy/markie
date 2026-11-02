<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "requires" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_requires',
  label: new TranslatableMarkup('Requires'),
  name: 'dcterms.requires',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that is required by the described resource to support its function, delivery, or coherence.'),
  weight: 6,
)]
class Requires extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
