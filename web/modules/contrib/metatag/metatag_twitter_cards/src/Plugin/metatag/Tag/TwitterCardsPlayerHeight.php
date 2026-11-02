<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards player height metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_player_height',
  label: new TranslatableMarkup('Media player height'),
  name: 'twitter:player:height',
  group: 'twitter_cards',
  type: MetatagType::Integer,
  description: new TranslatableMarkup('The height of the media player iframe, in pixels. Required when using the Player Card type.'),
  weight: 402,
)]
class TwitterCardsPlayerHeight extends MetaNameBase {
}
