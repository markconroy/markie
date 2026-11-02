<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app's custom URL scheme for ipad metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_url_ipad',
  label: new TranslatableMarkup("iPad app's custom URL scheme"),
  name: 'twitter:app:url:ipad',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup("The iPad app's custom URL scheme (must include \"://\" after the scheme name)."),
  weight: 305,
)]
class TwitterCardsAppUrlIpad extends MetaNameBase {
}
