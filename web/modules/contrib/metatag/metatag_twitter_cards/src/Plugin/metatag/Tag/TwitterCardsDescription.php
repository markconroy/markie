<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards site's description.
 */
#[MetatagTag(
  id: 'twitter_cards_description',
  label: new TranslatableMarkup('Description'),
  name: 'twitter:description',
  group: 'twitter_cards',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A description that concisely summarizes the content of the page, as appropriate for presentation within a Tweet. Do not re-use the title text as the description, or use this field to describe the general services provided by the website. The string will be truncated, by Twitter, at the word to 200 characters.'),
  weight: 2,
  trimmable: TRUE,
)]
class TwitterCardsDescription extends MetaNameBase {
}
