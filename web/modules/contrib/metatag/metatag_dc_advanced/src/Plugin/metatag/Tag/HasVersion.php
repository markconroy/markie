<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "hasVersion" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_has_version',
  label: new TranslatableMarkup('Has Version'),
  name: 'dcterms.hasVersion',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that is a version, edition, or adaptation of the described resource.'),
  weight: 6,
)]
class HasVersion extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
