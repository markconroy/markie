<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards site's account metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_site',
  label: new TranslatableMarkup("Site's Twitter account"),
  name: 'twitter:site',
  group: 'twitter_cards',
  type: MetatagType::String,
  description: new TranslatableMarkup("The @username for the website, which will be displayed in the Card's footer; must include the @ symbol."),
  weight: 2,
)]
class TwitterCardsSite extends MetaNameBase {
}
