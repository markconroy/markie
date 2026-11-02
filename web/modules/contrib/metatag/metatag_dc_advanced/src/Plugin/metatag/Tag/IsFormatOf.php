<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "isFormatOf" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_is_format_of',
  label: new TranslatableMarkup('Is Format Of'),
  name: 'dcterms.isFormatOf',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that is substantially the same as the described resource, but in another format.'),
  weight: 6,
)]
class IsFormatOf extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
