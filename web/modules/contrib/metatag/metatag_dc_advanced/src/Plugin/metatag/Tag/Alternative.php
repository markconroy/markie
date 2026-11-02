<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "alternative" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_alternative',
  label: new TranslatableMarkup('Alternative Title'),
  name: 'dcterms.alternative',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An alternative name for the resource. The distinction between titles and alternative titles is application-specific.'),
  weight: 6,
)]
class Alternative extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
