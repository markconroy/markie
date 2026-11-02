<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Type" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_type',
  label: new TranslatableMarkup('Type'),
  name: 'dcterms.type',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The nature or genre of the resource. Recommended best practice is to use a controlled vocabulary such as the DCMI Type Vocabulary [DCMITYPE]. To describe the file format, physical medium, or dimensions of the resource, use the Format element.'),
  weight: 8,
  trimmable: TRUE,
)]
class Type extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
