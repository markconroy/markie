<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards app name for ipad metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_app_name_ipad',
  label: new TranslatableMarkup('iPad app name'),
  name: 'twitter:app:name:ipad',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('The name of the iPad app.'),
  weight: 303,
)]
class TwitterCardsAppNameIpad extends MetaNameBase {
}
