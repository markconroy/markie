<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app id for Google Play metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_id_googleplay',
  label: new TranslatableMarkup('Google Play app ID'),
  name: 'twitter:app:id:googleplay',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('Your app ID in the Google Play Store (i.e. "com.android.app").'),
  weight: 307,
)]
class TwitterCardsAppIdGooglePlay extends MetaNameBase {
}
