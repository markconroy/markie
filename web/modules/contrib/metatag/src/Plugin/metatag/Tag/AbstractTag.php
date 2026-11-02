<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Abstract" meta tag.
 */
#[MetatagTag(
  id: 'abstract',
  label: new TranslatableMarkup('Abstract'),
  name: 'abstract',
  group: 'basic',
  type: MetatagType::Label,
  description: new TranslatableMarkup("A brief and concise summary of the page's content, preferably 150 characters or less. Where as the description meta tag may be used by search engines to display a snippet about the page in search results, the abstract tag may be used to archive a summary about the page. This meta tag is <em>no longer</em> supported by major search engines."),
  weight: 3,
  long: TRUE,
  trimmable: TRUE,
)]
class AbstractTag extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
