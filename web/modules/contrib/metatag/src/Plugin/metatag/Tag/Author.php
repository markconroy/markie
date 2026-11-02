<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The advanced Author meta tag.
 */
#[MetatagTag(
  id: 'author',
  label: new TranslatableMarkup('Author'),
  name: 'author',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Defines the author of a page.'),
  weight: 4,
  secure: FALSE,
  multiple: FALSE,
)]
class Author extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
