<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;

/**
 * Base normalizer used in all JSON:API normalizers.
 *
 * @internal
 */
abstract class NormalizerBase extends SerializationNormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $format = 'api_json';

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL) {
    // The parent implementation allows format-specific normalizers to be used
    // for formatless normalization. The JSON:API module wants to be cautious.
    // Hence it only allows its normalizers to be used for the JSON:API format,
    // to avoid JSON:API-specific normalizations showing up in the REST API.
    return $format === $this->format;
  }

}
