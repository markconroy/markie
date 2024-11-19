<?php

namespace Drupal\ai\Utility;

/**
 * Utility for chunking text in an NLP friendly manner.
 */
class TextChunker implements TextChunkerInterface {

  /**
   * Construct the text chunker utility.
   *
   * @param \Drupal\ai\Utility\TokenizerInterface $tokenizer
   *   The tokenizer service.
   */
  public function __construct(
    protected TokenizerInterface $tokenizer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function setModel(string $model): void {
    $this->tokenizer->setModel($model);
  }

  /**
   * {@inheritdoc}
   */
  public function chunkText(string $text, int $maxSize, int $minOverlap): array {
    if (
      !method_exists($this->tokenizer, 'getEncodedChunks')
      || !method_exists($this->tokenizer, 'decodeChunk')
    ) {
      throw new \Exception('This text chunker expects that the encoder has getChunks and decodeChunk methods.');
    }

    // If we have overlap set, the max chunk size should be reduced by the
    // minimum overlap.
    $calculated_max_size = $maxSize - $minOverlap;
    if ($calculated_max_size <= 0) {
      throw new \Exception('The minimum overlap cannot be equal to or exceed the maximum chunk size.');
    }
    $chunks = $this->tokenizer->getEncodedChunks($text, $calculated_max_size);

    // If we have overlap, we should get the encoding of the chunks to be able
    // to retrieve the preceding tokens and prepend them to the current chunk.
    if ($minOverlap > 0) {

      // Ensure that chunks are numerical indexed to handle overlap calculation.
      $chunks = array_values($chunks);
      foreach ($chunks as $key => $chunk) {

        // Only do this if there is a preceding chunk.
        if ($key < 1 || !isset($chunks[$key - 1])) {
          continue;
        }

        // Ensure numeric index for later merging to be reliable.
        $chunk = array_values($chunk);
        $preceding_chunk = array_values($chunks[$key - 1]);

        // Get the desired number of tokens from the end of the preceding
        // chunk. Get as much of the minimum overlap as available.
        $count_to_retrieve = min(count($preceding_chunk), $minOverlap);
        $retrieved_preceding_tokens = [];
        if ($count_to_retrieve) {
          $retrieved_preceding_tokens = array_slice($preceding_chunk, (-1 * $count_to_retrieve));
        }

        // Prepend them to the chunk.
        if ($retrieved_preceding_tokens) {
          $chunks[$key] = array_merge($retrieved_preceding_tokens, $chunk);
        }
        else {
          $chunks[$key] = $chunk;
        }
      }
    }

    // Convert the chunk tokens back into strings.
    foreach ($chunks as $key => $chunk) {
      $chunks[$key] = trim($this->tokenizer->decodeChunk($chunk));
    }

    return $chunks;
  }

}
