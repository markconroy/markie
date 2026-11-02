<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Description" meta tag.
 */
#[MetatagTag(
  id: 'description',
  label: new TranslatableMarkup('Description'),
  name: 'description',
  group: 'basic',
  type: MetatagType::Label,
  description: new TranslatableMarkup("A brief and concise summary of the page's content that is a maximum of 160 characters in length. The description meta tag may be used by search engines to display a snippet about the page in search results."),
  weight: 2,
  long: TRUE,
  trimmable: TRUE,
)]
class Description extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
