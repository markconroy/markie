<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Identifier" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_identifier',
  label: new TranslatableMarkup('Identifier'),
  name: 'dcterms.identifier',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An unambiguous reference to the resource within a given context. Recommended best practice is to identify the resource by means of a string conforming to a formal identification system.'),
  weight: 10,
)]
class Identifier extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
