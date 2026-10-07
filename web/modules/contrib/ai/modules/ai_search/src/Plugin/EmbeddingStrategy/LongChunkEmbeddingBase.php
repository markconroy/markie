<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Extends EmbeddingBase with support for long-running chunk processing.
 *
 * This intermediate class adds chunk-offset awareness to getEmbedding() so
 * that the backend can call it multiple times — once per batch run — each time
 * receiving only a slice of the item's chunks. This prevents PHP timeout on
 * very large items without requiring any interface changes.
 *
 * Usage by the backend:
 * @code
 * if (method_exists($strategy, 'setChunkOffset')) {
 *   $strategy->setChunkOffset($offset);
 *   $client->setSkipDeleteItemIds($in_progress_ids);
 * }
 * @endcode
 *
 * @internal This class exists only in the 1.0.x branch to preserve backward
 *   compatibility for sites that extend EmbeddingBase. In 2.0.x this
 *   functionality is built directly into the interface and EmbeddingBase.
 */
abstract class LongChunkEmbeddingBase extends EmbeddingBase {

  /**
   * The chunk offset to start from during the current indexing batch run.
   *
   * Set by SearchApiAiSearchBackend before each getEmbedding() call when
   * processing items in multiple batch runs.
   *
   * @var int
   */
  protected int $chunkOffset = 0;

  /**
   * Maximum number of chunks to embed per indexing batch run.
   *
   * @var int
   */
  protected int $maxChunksPerBatch = 10;

  /**
   * Sets the chunk offset for the next getEmbedding() call.
   *
   * @param int $offset
   *   The index of the first chunk to embed in this batch run.
   */
  public function setChunkOffset(int $offset): void {
    $this->chunkOffset = $offset;
  }

  /**
   * Returns the maximum number of chunks to process per batch run.
   *
   * @return int
   *   The per-batch chunk limit.
   */
  public function getMaxChunksPerBatch(): int {
    return $this->maxChunksPerBatch;
  }

  /**
   * {@inheritDoc}
   *
   * Overrides EmbeddingBase::getEmbedding() to process only a slice of the
   * item's chunks per call. The slice is determined by $chunkOffset and
   * $maxChunksPerBatch. Chunk IDs incorporate the absolute offset so that
   * successive batches produce distinct, non-colliding vector IDs.
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
    IndexInterface $index,
  ): array {
    $this->init($embedding_engine, $chat_model, $configuration);
    [$title, $contextual_content, $main_content] = $this->groupFieldData($fields, $index);
    $title = $this->resolveEntityTitle($title, $fields, $search_api_item);
    $title_in_contextual = $this->isTitleInContextual($fields, $index);
    $all_chunks = $this->getChunks($title, $main_content, $contextual_content, $title_in_contextual, $index);

    $offset = $this->chunkOffset;
    $slice = array_slice($all_chunks, $offset, $this->maxChunksPerBatch);

    $metadata = $this->buildBaseMetadata($fields, $index);
    $raw_embeddings = $this->getRawEmbeddings($slice);
    $embeddings = [];
    foreach ($slice as $relative_key => $chunk) {
      $absolute_key = $offset + $relative_key;
      if (!isset($raw_embeddings[$relative_key])) {
        continue;
      }
      $metadata = $this->addContentToMetadata($metadata, $chunk, $index);
      $embeddings[] = [
        'id' => $search_api_item->getId() . ':' . $absolute_key,
        'values' => $raw_embeddings[$relative_key],
        'metadata' => $metadata,
      ];
    }

    return $embeddings;
  }

}
