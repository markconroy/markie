<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "mediator" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_mediator',
  label: new TranslatableMarkup('Mediator'),
  name: 'dcterms.mediator',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An entity that mediates access to the resource and for whom the resource is intended or useful. In an educational context, a mediator might be a parent, teacher, teaching assistant, or care-giver.'),
  weight: 6,
)]
class Mediator extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
