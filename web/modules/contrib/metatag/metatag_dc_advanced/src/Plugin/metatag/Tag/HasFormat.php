<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "hasFormat" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_has_format',
  label: new TranslatableMarkup('Has Format'),
  name: 'dcterms.hasFormat',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that is substantially the same as the pre-existing described resource, but in another format.'),
  weight: 6,
)]
class HasFormat extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
