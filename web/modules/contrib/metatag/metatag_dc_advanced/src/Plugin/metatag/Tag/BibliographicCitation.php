<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "bibliographicCitation" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_bibliographic_citation',
  label: new TranslatableMarkup('Bibliographic Citation'),
  name: 'dcterms.bibliographicCitation',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A bibliographic reference for the resource. Recommended practice is to include sufficient bibliographic detail to identify the resource as unambiguously as possible.'),
  weight: 6,
)]
class BibliographicCitation extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
