<?php

namespace Drupal\metatag_open_graph_products\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'product:retailer_item_id' meta tag.
 */
#[MetatagTag(
  id: 'product_retailer_item_id',
  label: new TranslatableMarkup('Retailer Item ID'),
  name: 'product:retailer_item_id',
  group: 'open_graph_products',
  type: MetatagType::String,
  description: new TranslatableMarkup('The ID of the product as provided by the retailer.'),
  weight: 1,
)]
class ProductRetailerItemId extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
