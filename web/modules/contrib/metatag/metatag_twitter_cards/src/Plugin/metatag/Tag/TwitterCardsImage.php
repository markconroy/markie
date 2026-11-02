<?php

namespace Drupal\metatag_twitter_cards\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Twitter Cards image metatag.
 */
#[MetatagTag(
  id: 'twitter_cards_image',
  label: new TranslatableMarkup('Image URL'),
  name: 'twitter:image',
  group: 'twitter_cards',
  type: MetatagType::Image,
  description: new TranslatableMarkup("The URL to a unique image representing the content of the page. Do not use a generic image such as your website logo, author photo, or other image that spans multiple pages. Images larger than 120x120px will be resized and cropped square based on longest dimension. Images smaller than 60x60px will not be shown. If the 'type' is set to Photo then the image must be at least 280x150px."),
  weight: 7,
  absolute_url: TRUE,
)]
class TwitterCardsImage extends MetaNameBase {
}
