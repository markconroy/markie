<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Open Graph "Site name" meta tag.
 */
#[MetatagTag(
  id: 'og_site_name',
  label: new TranslatableMarkup('Site name'),
  name: 'og:site_name',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A human-readable name for the site, e.g., <em>IMDb</em>.'),
  weight: 1,
  trimmable: TRUE,
)]
class OgSiteName extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
