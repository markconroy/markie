<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app name for iphone metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_name_iphone',
  label: new TranslatableMarkup('iPhone app name'),
  name: 'twitter:app:name:iphone',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('The name of the iPhone app.'),
  weight: 301,
)]
class TwitterCardsAppNameIphone extends MetaNameBase {
}
