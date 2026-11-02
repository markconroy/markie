<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Description" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_description',
  label: new TranslatableMarkup('Description'),
  name: 'dcterms.description',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('An account of the resource. Description may include but is not limited to: an abstract, a table of contents, a graphical representation, or a free-text account of the resource.'),
  weight: 4,
  trimmable: TRUE,
)]
class Description extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
