<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Description" meta tag.
 */
#[MetatagTag(
  id: 'og_description',
  label: new TranslatableMarkup('Description'),
  name: 'og:description',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A one to two sentence description of the content.'),
  weight: 6,
  long: TRUE,
  trimmable: TRUE,
)]
class OgDescription extends MetaPropertyBase {

}
