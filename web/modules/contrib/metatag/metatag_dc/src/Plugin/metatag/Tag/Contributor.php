<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Contributor" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_contributor',
  label: new TranslatableMarkup('Contributor'),
  name: 'dcterms.contributor',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An entity responsible for making contributions to the resource. Examples of a Contributor include a person, an organization, or a service. Typically, the name of a Contributor should be used to indicate the entity.'),
  weight: 6,
)]
class Contributor extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
