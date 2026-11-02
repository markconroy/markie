<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Coverage" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_coverage',
  label: new TranslatableMarkup('Coverage'),
  name: 'dcterms.coverage',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The spatial or temporal topic of the resource, the spatial applicability of the resource, or the jurisdiction under which the resource is relevant. Spatial topic and spatial applicability may be a named place or a location specified by its geographic coordinates. Temporal topic may be a named period, date, or date range. A jurisdiction may be a named administrative entity or a geographic place to which the resource applies. Recommended best practice is to use a controlled vocabulary such as the Thesaurus of Geographic Names [TGN]. Where appropriate, named places or time periods can be used in preference to numeric identifiers such as sets of coordinates or date ranges.'),
  weight: 14,
)]
class Coverage extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
