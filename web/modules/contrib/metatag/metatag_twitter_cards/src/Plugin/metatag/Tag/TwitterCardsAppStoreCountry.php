<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app store country code metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_store_country',
  label: new TranslatableMarkup('App store country'),
  name: 'twitter:app:country',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('If your application is not available in the US App Store, you must set this value to the two-letter country code for the App Store that contains your application.'),
  weight: 300,
)]
class TwitterCardsAppStoreCountry extends MetaNameBase {
}
