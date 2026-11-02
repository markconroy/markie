<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The basic "Rights" meta tag.
 */
#[MetatagTag(
  id: 'rights',
  label: new TranslatableMarkup('Rights'),
  name: 'rights',
  group: 'advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup("Details about intellectual property, such as copyright or trademarks; does not automatically protect the site's content or intellectual property."),
  weight: 5,
)]
class Rights extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
