<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards image alternative text metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_image_alt',
  label: new TranslatableMarkup('Image alternative text'),
  name: 'twitter:image:alt',
  group: 'twitter_cards',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The alternative text of the image being linked to. Limited to 420 characters.'),
  weight: 7,
)]
class TwitterCardsImageAlt extends MetaNameBase {
}
