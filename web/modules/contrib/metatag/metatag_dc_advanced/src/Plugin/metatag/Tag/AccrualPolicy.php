<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "accrualPolicy" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_accrual_policy',
  label: new TranslatableMarkup('Accrual Policy'),
  name: 'dcterms.accrualPolicy',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The policy governing the addition of items to a collection.'),
  weight: 6,
)]
class AccrualPolicy extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
