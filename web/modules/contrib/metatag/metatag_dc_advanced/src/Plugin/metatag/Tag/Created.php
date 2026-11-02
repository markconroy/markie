<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "created" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_created',
  label: new TranslatableMarkup('Date Created'),
  name: 'dcterms.created',
  group: 'dublin_core_advanced',
  // @todo Should this be a Date instead?
  type: MetatagType::Label,
  description: new TranslatableMarkup('Date of creation of the resource.'),
  weight: 6,
)]
class Created extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
