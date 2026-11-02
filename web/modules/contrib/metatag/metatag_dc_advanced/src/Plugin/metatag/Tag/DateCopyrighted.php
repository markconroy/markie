<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "dateCopyrighted" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_date_copyrighted',
  label: new TranslatableMarkup('Date Copyrighted'),
  name: 'dcterms.dateCopyrighted',
  group: 'dublin_core_advanced',
  // @todo Should this be a date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date of copyright.'),
  weight: 6,
)]
class DateCopyrighted extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
