<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'google' meta tag.
 */
#[MetatagTag(
  id: 'google',
  label: new TranslatableMarkup('Google'),
  name: 'google',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    "This meta tag communicates with Google. There are currently two directives supported: 'nositelinkssearchbox' to not to show the sitelinks search box, and 'notranslate' to ask Google not to offer a translation of the page. Both options may be added, just separate them with a comma. See <a href=\":url\">meta tags that Google understands</a> for further details.",
    [
      ':url' => 'https://developers.google.com/search/docs/crawling-indexing/special-tags',
    ],
  ),
  weight: 5,
)]
class Google extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
