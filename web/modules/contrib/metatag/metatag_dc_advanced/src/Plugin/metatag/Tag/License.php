<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "license" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_license',
  label: new TranslatableMarkup('License'),
  name: 'dcterms.license',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A legal document giving official permission to do something with the resource.'),
  weight: 6,
)]
class License extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
