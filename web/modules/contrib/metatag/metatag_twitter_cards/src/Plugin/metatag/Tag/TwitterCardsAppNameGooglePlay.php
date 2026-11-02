<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app name Google Play metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_name_googleplay',
  label: new TranslatableMarkup('Google Play app name'),
  name: 'twitter:app:name:googleplay',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('The name of the app in the Google Play app store.'),
  weight: 306,
)]
class TwitterCardsAppNameGooglePlay extends MetaNameBase {
}
