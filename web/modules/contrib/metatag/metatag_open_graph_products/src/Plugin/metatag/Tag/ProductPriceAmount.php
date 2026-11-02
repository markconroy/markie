<?php

namespace Drupal\metatag_open_graph_products\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'product:price:amount' meta tag.
 */
#[MetatagTag(
  id: 'product_price_amount',
  label: new TranslatableMarkup('Product price amount'),
  name: 'product:price:amount',
  group: 'open_graph_products',
  type: MetatagType::String,
  description: new TranslatableMarkup('The price amount of the product.'),
  weight: 1,
)]
class ProductPriceAmount extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
