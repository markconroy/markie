<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for providers that support multi embeddings.
 *
 * This is a separate, opt-in capability so that existing embeddings providers
 * are not forced to implement it (no breaking change to EmbeddingsInterface).
 * Providers that can embed multiple inputs in a single request implement this
 * interface; callers detect support with
 * `instanceof EmbeddingsCollectionInterface`.
 *
 * It deliberately omits the #[OperationType] attribute: multi embeddings is not
 * an independently configurable operation (it reuses the embeddings provider),
 * so it must not appear as a discoverable operation type with its own default
 * provider in ai.settings. Extending OperationTypeInterface is still required
 * so ProviderProxy treats embeddingsCollection() as a trigger method and routes
 * it through the full pipeline (events, config normalization, tags, logging).
 */
interface EmbeddingsCollectionInterface extends OperationTypeInterface {

  /**
   * Generate embeddings for multiple inputs in a single request.
   *
   * @param \Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInput $input
   *   The multi embeddings input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionOutput
   *   The embeddings output. The normalized value is a list of vectors, one
   *   per input in the same order.
   */
  public function embeddingsCollection(EmbeddingsCollectionInput $input, string $model_id, array $tags = []): EmbeddingsCollectionOutput;

}
