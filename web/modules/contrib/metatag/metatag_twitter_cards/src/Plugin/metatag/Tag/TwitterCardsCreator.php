<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards creator meta tag.
 */
#[MetatagTag(
  id: 'twitter_cards_creator',
  label: new TranslatableMarkup("Creator's Twitter account"),
  name: 'twitter:creator',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup('The @username for the content creator/author for this page, including the @ symbol.'),
  weight: 4,
)]
class TwitterCardsCreator extends MetaNameBase {
}
