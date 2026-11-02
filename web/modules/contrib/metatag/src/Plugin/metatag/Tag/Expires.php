<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The Expires meta tag.
 */
#[MetatagTag(
  id: 'expires',
  label: new TranslatableMarkup('Expires'),
  name: 'expires',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    "Control when the browser's internal cache of the current page should expire. The date must to be an <a href=\":url\">RFC-1123</a>-compliant date string that is represented in Greenwich Mean Time (GMT), e.g. 'Thu, 01 Sep 2016 00:12:56 GMT'. Set to '0' to stop the page being cached entirely.",
    [
      ':url' => 'https://datatracker.ietf.org/doc/html/rfc1123',
    ],
  ),
  weight: 11,
)]
class Expires extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
