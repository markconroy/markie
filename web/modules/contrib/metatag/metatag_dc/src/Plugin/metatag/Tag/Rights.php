<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Rights" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_rights',
  label: new TranslatableMarkup('Rights'),
  name: 'dcterms.rights',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Information about rights held in and over the resource. Typically, rights information includes a statement about various property rights associated with the resource, including intellectual property rights.'),
  weight: 15,
)]
class Rights extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
