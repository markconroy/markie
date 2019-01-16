<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;

/**
 * Helps normalize fields in compliance with the JSON:API spec.
 *
 * @internal
 */
class FieldNormalizerValue implements FieldNormalizerValueInterface {

  use CacheableDependencyTrait;
  use CacheableDependenciesMergerTrait;

  /**
   * The values.
   *
   * @var array
   */
  protected $values;

  /**
   * The field cardinality.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The property type. Either: 'attributes' or `relationships'.
   *
   * @var string
   */
  protected $propertyType;

  /**
   * Instantiate a FieldNormalizerValue object.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $field_access_result
   *   The field access result.
   * @param \Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue[] $values
   *   The normalized result.
   * @param int $cardinality
   *   The cardinality of the field list.
   * @param string $property_type
   *   The property type of the field: 'attributes' or 'relationships'.
   */
  public function __construct(AccessResultInterface $field_access_result, array $values, $cardinality, $property_type) {
    assert($property_type === 'attributes' || $property_type === 'relationships');
    $this->setCacheability(static::mergeCacheableDependencies(array_merge([$field_access_result], $values)));

    $this->values = $values;
    $this->cardinality = $cardinality;
    $this->propertyType = $property_type;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    if (empty($this->values)) {
      return NULL;
    }

    if ($this->cardinality == 1) {
      assert(count($this->values) === 1);
      return $this->values[0] instanceof FieldItemNormalizerValue
        ? $this->values[0]->rasterizeValue() : NULL;
    }

    return array_map(function ($value) {
      return $value instanceof FieldItemNormalizerValue ? $value->rasterizeValue() : NULL;
    }, $this->values);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyType() {
    return $this->propertyType;
  }

}
