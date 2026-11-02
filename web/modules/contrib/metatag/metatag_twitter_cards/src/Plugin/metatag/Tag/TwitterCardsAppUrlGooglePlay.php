<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app's custom URL scheme for Google Play metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_url_googleplay',
  label: new TranslatableMarkup("Google Play app's custom URL scheme"),
  name: 'twitter:app:url:googleplay',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup("The Google Play app's custom URL scheme (must include \"://\" after the scheme name)."),
  weight: 308,
)]
class TwitterCardsAppUrlGooglePlay extends MetaNameBase {
}
