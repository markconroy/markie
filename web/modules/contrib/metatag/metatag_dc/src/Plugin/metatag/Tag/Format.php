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
  id: 'dcterms_format',
  label: new TranslatableMarkup('Format'),
  name: 'dcterms.format',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The file format, physical medium, or dimensions of the resource. Examples of dimensions include size and duration. Recommended best practice is to use a controlled vocabulary such as the list of Internet Media Types [MIME].'),
  weight: 9,
)]
class Format extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
