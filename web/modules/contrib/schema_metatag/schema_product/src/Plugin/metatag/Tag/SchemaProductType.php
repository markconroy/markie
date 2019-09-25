<?php

namespace Drupal\schema_product\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_product_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The type of product."),
 *   name = "@type",
 *   group = "schema_product",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaProductType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
      'Product',
      'IndividualProduct',
      'ProductModel',
      'SomeProducts',
      'Vehicle',
      '- Car',
    ];
  }

}
