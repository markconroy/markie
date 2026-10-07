<?php

namespace Drupal\ai\OperationType\Embeddings;

/**
 * Data transfer output object for embeddings output.
 *
 * This class is created only to distinguish between output with multiple
 * vectors. This will make sure that the backwards compatibility for parent
 * class is secured.
 */
class EmbeddingsCollectionOutput extends EmbeddingsOutput {

  /**
   * Gets the embedding vectors, one per input, in input order.
   *
   * @return array<int, array<float>>
   *   A list of vectors (each vector a list of floats).
   */
  // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
  public function getNormalized(): array {
    return parent::getNormalized();
  }

}
