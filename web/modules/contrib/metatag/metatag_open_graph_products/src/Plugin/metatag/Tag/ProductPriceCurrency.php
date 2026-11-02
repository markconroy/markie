<?php

namespace Drupal\metatag_open_graph_products\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'product:price:currency' meta tag.
 */
#[MetatagTag(
  id: 'product_price_currency',
  label: new TranslatableMarkup('Product price currency'),
  name: 'product:price:currency',
  group: 'open_graph_products',
  type: MetatagType::String,
  description: new TranslatableMarkup('The price currency of the product.'),
  weight: 2,
)]
class ProductPriceCurrency extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
