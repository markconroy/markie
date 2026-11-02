<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Creator" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_creator',
  label: new TranslatableMarkup('Creator'),
  name: 'dcterms.creator',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An entity primarily responsible for making the resource. Examples of a Creator include a person, an organization, or a service. Typically, the name of a Creator should be used to indicate the entity.'),
  weight: 2,
)]
class Creator extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
