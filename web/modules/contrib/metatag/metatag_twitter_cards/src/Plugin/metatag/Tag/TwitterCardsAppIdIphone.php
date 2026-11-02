<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app id for iphone metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_id_iphone',
  label: new TranslatableMarkup('iPhone app ID'),
  name: 'twitter:app:id:iphone',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup("String value, should be the numeric representation of your iPhone app's ID in the App Store."),
  weight: 302,
)]
class TwitterCardsAppIdIphone extends MetaNameBase {
}
