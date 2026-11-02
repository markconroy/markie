<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "issued" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_issued',
  label: new TranslatableMarkup('Date Issued'),
  name: 'dcterms.issued',
  group: 'dublin_core_advanced',
  // @todo Should this be a date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date of formal issuance (e.g., publication) of the resource.'),
  weight: 6,
)]
class Issued extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
