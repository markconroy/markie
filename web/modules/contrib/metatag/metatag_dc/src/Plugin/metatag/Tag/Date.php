<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Date" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_date',
  label: new TranslatableMarkup('Date'),
  name: 'dcterms.date',
  group: 'dublin_core',
  // @todo Should this be a Date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup('A point or period of time associated with an event in the lifecycle of the resource. Date may be used to express temporal information at any level of granularity. Recommended best practice is to use an encoding scheme, such as the W3CDTF profile of ISO 8601 [W3CDTF].'),
  weight: 7,
)]
class Date extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
