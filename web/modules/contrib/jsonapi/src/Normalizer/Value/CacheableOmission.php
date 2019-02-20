<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Represents the cacheability associated with the omission of a value.
 *
 * @internal
 */
final class CacheableOmission extends CacheableNormalization {

  /**
   * CacheableOmission constructor.
   *
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   Cacheability related to the omission of the normalization. For example,
   *   if a field is omitted because of an access result that varies by the
   *   `user.permissions` cache context, we need to associate that information
   *   with the response so that it will appear for a user *with* the
   *   appropriate permissions for that field.
   */
  public function __construct(CacheableDependencyInterface $cacheability) {
    parent::__construct($cacheability, NULL);
  }

  /**
   * A CacheableOmission should never have its normalization retrieved.
   */
  public function getNormalization() {
    throw new \LogicException('A CacheableOmission should never have its normalization retrieved.');
  }

}
