<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards creator id metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_creator_id',
  label: new TranslatableMarkup("Creator's Twitter account"),
  name: 'twitter:creator:id',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('The numerical Twitter account ID for the content creator/author for this page.'),
  weight: 4,
)]
class TwitterCardsCreatorId extends MetaNameBase {
}
