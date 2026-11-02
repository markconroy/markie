<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "dateSubmitted" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_date_submitted',
  label: new TranslatableMarkup('Date Submitted'),
  name: 'dcterms.dateSubmitted',
  group: 'dublin_core_advanced',
  // @todo Should this be a date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date of submission of the resource. Examples of resources to which a Date Submitted may be relevant are a thesis (submitted to a university department) or an article (submitted to a journal).'),
  weight: 6,
)]
class DateSubmitted extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
