<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards player stream content-type metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_player_stream_content_type',
  label: new TranslatableMarkup('MP4 media stream MIME-type'),
  name: 'twitter:player:stream:content_type',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup(
    'The MIME type for the media contained in the stream URL, as defined by <a href=":url"">RFC 4337</a>.',
    [":url" => "https://tools.ietf.org/rfc/rfc4337.txt"],
  ),
  weight: 404,
)]
class TwitterCardsPlayerStreamContentType extends MetaNameBase {
}
