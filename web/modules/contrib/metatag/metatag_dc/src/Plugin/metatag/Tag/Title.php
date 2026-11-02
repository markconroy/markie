<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Title" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_title',
  label: new TranslatableMarkup('Title'),
  name: 'dcterms.title',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The name given to the resource.'),
  weight: 1,
  trimmable: TRUE,
)]
class Title extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
