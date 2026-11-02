<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "tableOfContents" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_table_of_contents',
  label: new TranslatableMarkup('Table Of Contents'),
  name: 'dcterms.tableOfContents',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A list of subunits of the resource.'),
  weight: 6,
)]
class TableOfContents extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
