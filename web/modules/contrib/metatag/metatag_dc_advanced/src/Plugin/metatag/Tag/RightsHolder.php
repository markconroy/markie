<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "rightsHolder" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_rights_holder',
  label: new TranslatableMarkup('Rights Holder'),
  name: 'dcterms.rightsHolder',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A person or organization owning or managing rights over the resource.'),
  weight: 6,
)]
class RightsHolder extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
