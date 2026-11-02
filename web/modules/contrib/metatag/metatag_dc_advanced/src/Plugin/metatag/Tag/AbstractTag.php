<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Abstract" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_abstract',
  label: new TranslatableMarkup('Abstract'),
  name: 'dcterms.abstract',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A summary of the resource.'),
  weight: 6,
)]
class AbstractTag extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
