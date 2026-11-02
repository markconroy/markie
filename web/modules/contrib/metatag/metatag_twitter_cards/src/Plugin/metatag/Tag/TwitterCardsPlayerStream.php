<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards player stream url metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_player_stream',
  label: new TranslatableMarkup('MP4 media stream URL'),
  name: 'twitter:player:stream',
  group: 'twitter_cards',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('The full URL for an MP4 video (h.264) or audio (AAC) stream, takes precedence over the other media player field.'),
  weight: 403,
)]
class TwitterCardsPlayerStream extends MetaNameBase {
}
