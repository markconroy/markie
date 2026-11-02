<?php

namespace Drupal\metatag_open_graph_products\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'product:condition' meta tag.
 */
#[MetatagTag(
  id: 'product_condition',
  label: new TranslatableMarkup('Product condition'),
  name: 'product:condition',
  group: 'open_graph_products',
  type: MetatagType::String,
  description: new TranslatableMarkup('The condition of the product.'),
  weight: 1,
)]
class ProductCondition extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
