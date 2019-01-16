<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Interface for value objects used in the JSON:API normalization process.
 *
 * @internal
 */
interface ValueExtractorInterface {

  /**
   * Get the rasterized value.
   *
   * @return mixed
   *   The value.
   */
  public function rasterizeValue();

}
