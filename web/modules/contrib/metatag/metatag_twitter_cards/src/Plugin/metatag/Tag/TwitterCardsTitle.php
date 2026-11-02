<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards title metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_title',
  label: new TranslatableMarkup('Title'),
  name: 'twitter:title',
  group: 'twitter_cards',
  type: MetatagType::Label,
  description: new TranslatableMarkup("The page's title, which should be concise; it will be truncated at 70 characters by Twitter. This field is required unless this the 'type' field is set to 'photo'."),
  weight: 2,
  trimmable: TRUE,
)]
class TwitterCardsTitle extends MetaNameBase {
}
