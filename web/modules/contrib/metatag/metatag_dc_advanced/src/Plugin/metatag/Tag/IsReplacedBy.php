<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "isReplacedBy" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_is_replaced_by',
  label: new TranslatableMarkup('Is Replaced By'),
  name: 'dcterms.isReplacedBy',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A related resource that supplants, displaces, or supersedes the described resource.'),
  weight: 6,
)]
class IsReplacedBy extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
