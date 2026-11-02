<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Keywords" meta tag.
 */
#[MetatagTag(
  id: 'keywords',
  label: new TranslatableMarkup('Keywords'),
  name: 'keywords',
  group: 'basic',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A comma-separated list of keywords about the page. This meta tag is <em>no longer</em> supported by most search engines.'),
  weight: 4,
)]
class Keywords extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
