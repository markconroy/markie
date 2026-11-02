<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "isVersionOf" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_is_version_of',
  label: new TranslatableMarkup('Is Version Of'),
  name: 'dcterms.isVersionOf',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource of which the described resource is a version, edition, or adaptation. Changes in version imply substantive changes in content rather than differences in format.'),
  weight: 6,
)]
class IsVersionOf extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
