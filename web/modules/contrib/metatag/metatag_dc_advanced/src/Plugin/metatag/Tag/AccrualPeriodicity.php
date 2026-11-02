<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "accrualPeriodicity" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_accrual_periodicity',
  label: new TranslatableMarkup('Accrual Periodicity'),
  name: 'dcterms.accrualPeriodicity',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The frequency with which items are added to a collection.'),
  weight: 6,
)]
class AccrualPeriodicity extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
