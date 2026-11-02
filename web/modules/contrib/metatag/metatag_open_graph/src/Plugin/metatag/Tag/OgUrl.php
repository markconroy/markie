<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "URL" meta tag.
 */
#[MetatagTag(
  id: 'og_url',
  label: new TranslatableMarkup('Page URL'),
  name: 'og:url',
  group: 'open_graph',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('Preferred page location or URL to help eliminate duplicate content for search engines, e.g., <em>https://www.imdb.com/title/tt0117500/</em>.'),
  weight: 3,
  absolute_url: TRUE,
)]
class OgUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
