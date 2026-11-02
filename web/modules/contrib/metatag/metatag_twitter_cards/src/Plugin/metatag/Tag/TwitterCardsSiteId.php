<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards site's id metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_site_id',
  label: new TranslatableMarkup("Site's Twitter account ID"),
  name: 'twitter:site:id',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup("The numerical Twitter account ID for the website, which will be displayed in the Card's footer."),
  weight: 3,
)]
class TwitterCardsSiteId extends MetaNameBase {
}
