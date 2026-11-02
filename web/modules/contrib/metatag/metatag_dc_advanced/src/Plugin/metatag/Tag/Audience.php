<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "audience" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_audience',
  label: new TranslatableMarkup('Audience'),
  name: 'dcterms.audience',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A class of entity for whom the resource is intended or useful.'),
  weight: 6,
)]
class Audience extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
