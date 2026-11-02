<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards player width metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_player_width',
  label: new TranslatableMarkup('Media player width'),
  name: 'twitter:player:width',
  group: 'twitter_cards',
  type: MetatagType::Integer,
  description: new TranslatableMarkup('The width of the media player iframe, in pixels. Required when using the Player Card type.'),
  weight: 401,
)]
class TwitterCardsPlayerWidth extends MetaNameBase {
}
