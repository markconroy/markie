<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app's custom URL scheme for iphone metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_url_iphone',
  label: new TranslatableMarkup("iPhone app's custom URL scheme"),
  name: 'twitter:app:url:iphone',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup("The iPhone app's custom URL scheme (must include \"://\" after the scheme name)."),
  weight: 302,
)]
class TwitterCardsAppUrlIphone extends MetaNameBase {
}
