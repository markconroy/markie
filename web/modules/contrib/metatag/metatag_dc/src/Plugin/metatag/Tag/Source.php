<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Source" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_source',
  label: new TranslatableMarkup('Source'),
  name: 'dcterms.source',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource from which the described resource is derived. The described resource may be derived from the related resource in whole or in part. Recommended best practice is to identify the related resource by means of a string conforming to a formal identification system.'),
  weight: 11,
)]
class Source extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
