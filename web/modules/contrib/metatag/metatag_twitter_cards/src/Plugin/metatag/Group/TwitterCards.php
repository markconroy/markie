<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The TwitterCards group.
 */
#[MetatagGroup(
  id: 'twitter_cards',
  label: new TranslatableMarkup('Twitter Cards'),
  description: new TranslatableMarkup(
    'A set of meta tags specially for controlling the summaries displayed when content is shared on <a href=":url">Twitter</a>.',
    [':url' => 'https://twitter.com/'],
  ),
  weight: 4,
)]
class TwitterCards extends GroupBase {
  // Inherits everything from Base.
}
