<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Publisher" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_publisher',
  label: new TranslatableMarkup('Publisher'),
  name: 'dcterms.publisher',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An entity responsible for making the resource available. Examples of a Publisher include a person, an organization, or a service. Typically, the name of a Publisher should be used to indicate the entity.'),
  weight: 5,
)]
class Publisher extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
