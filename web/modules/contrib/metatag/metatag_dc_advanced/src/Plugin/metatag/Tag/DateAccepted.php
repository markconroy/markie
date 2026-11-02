<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "dateAccepted" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_date_accepted',
  label: new TranslatableMarkup('Date Accepted'),
  name: 'dcterms.dateAccepted',
  group: 'dublin_core_advanced',
  // @todo Should this be a date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date of acceptance of the resource. Examples of resources to which a Date Accepted may be relevant are a thesis (accepted by a university department) or an article (accepted by a journal).'),
  weight: 6,
)]
class DateAccepted extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
