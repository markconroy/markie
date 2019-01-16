<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Helps normalize relationship items in compliance with the JSON:API spec.
 *
 * @internal
 */
class RelationshipItemNormalizerValue extends FieldItemNormalizerValue implements ValueExtractorInterface, CacheableDependencyInterface {

  use CacheableDependenciesMergerTrait;

  /**
   * Resource type.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType
   */
  protected $resourceType;

  /**
   * Instantiates a RelationshipItemNormalizerValue object.
   *
   * @param array $values
   *   The values.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $values_cacheability
   *   The cacheability of the normalized result. This cacheability is not part
   *   of $values because field items are normalized by Drupal core's
   *   serialization system, which was never designed with cacheability in mind.
   *   FieldItemNormalizer::normalize() must catch the out-of-band bubbled
   *   cacheability and then passes it to this value object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type of the target entity.
   */
  public function __construct(array $values, CacheableDependencyInterface $values_cacheability, ResourceType $resource_type) {
    parent::__construct($values, $values_cacheability);
    $this->resourceType = $resource_type;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    if (!$value = parent::rasterizeValue()) {
      return $value;
    }
    $rasterized_value = [
      'type' => $this->resourceType->getTypeName(),
      'id' => empty($value['target_uuid']) ? $value : $value['target_uuid'],
    ];

    if (!empty($value['meta'])) {
      $rasterized_value['meta'] = $value['meta'];
    }

    return $rasterized_value;
  }

}
