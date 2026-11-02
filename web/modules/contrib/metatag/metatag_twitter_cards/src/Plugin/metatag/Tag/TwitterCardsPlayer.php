<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards player metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_player',
  label: new TranslatableMarkup('Media player URL'),
  name: 'twitter:player',
  group: 'twitter_cards',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('The full URL for loading a media player, specifically an iframe for an embedded video rather than the URL to a page that contains a player. Required when using the Player Card type.'),
  weight: 400,
  absolute_url: TRUE,
)]
class TwitterCardsPlayer extends MetaNameBase {
}
