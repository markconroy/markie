<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Rating" meta tag.
 */
#[MetatagTag(
  id: 'rating',
  label: new TranslatableMarkup('Rating'),
  name: 'rating',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    'Used to rate content for audience appropriateness. This tag has little known influence on search engine rankings, but can be used by browsers, browser extensions, and apps. The most common options are general, mature, restricted, 14 years, safe for kids. If you follow the <a href=":docs">RTA Documentation</a> you should enter RTA-5042-1996-1400-1577-RTA.',
    [
      ':docs' => 'https://en.wikipedia.org/wiki/Content_rating',
    ],
  ),
  weight: 5,
)]
class Rating extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
