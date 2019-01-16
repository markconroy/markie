<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Interface to help normalize fields in compliance with the JSON:API spec.
 *
 * @internal
 */
interface FieldNormalizerValueInterface extends ValueExtractorInterface, CacheableDependencyInterface {

  /**
   * Gets the propertyType.
   *
   * @return mixed
   *   The propertyType.
   */
  public function getPropertyType();

}
