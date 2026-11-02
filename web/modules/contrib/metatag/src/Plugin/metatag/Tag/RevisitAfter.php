<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Rating" meta tag.
 */
#[MetatagTag(
  id: 'revisit_after',
  label: new TranslatableMarkup('Revisit After'),
  name: 'revisit-after',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    'Tell search engines when to index the page again. Very few search engines support this tag; it is more useful to use an <a href=":url">XML Sitemap</a> file.',
    [':url' => 'https://www.drupal.org/project/xmlsitemap'],
  ),
  weight: 8,
)]
class RevisitAfter extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
