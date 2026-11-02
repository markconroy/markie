<?php

namespace Drupal\metatag_open_graph_products\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'product:availability' meta tag.
 */
#[MetatagTag(
  id: 'product_availability',
  label: new TranslatableMarkup('Product availability'),
  name: 'product:availability',
  group: 'open_graph_products',
  type: MetatagType::String,
  description: new TranslatableMarkup('The availability of the product.'),
  weight: 1,
)]
class ProductAvailability extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
