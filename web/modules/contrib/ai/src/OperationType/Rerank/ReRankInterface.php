<?php

declare(strict_types=1);

namespace Drupal\ai\OperationType\Rerank;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for the ReRank plugin.
 */
#[OperationType(
  id: 'rerank',
  label: new TranslatableMarkup('Rerank'),
)]
interface ReRankInterface extends OperationTypeInterface {

  /**
   * Rerank a list of documents.
   *
   * The normalized output (ReRankOutput::getNormalized()) must be a list of
   * result items ordered from most to least relevant. Each item — whether an
   * associative array or an object — is expected to expose:
   * - index: (int, required) The zero-based position of the document in the
   *   ReRankInput inputs array that this result refers to. Consumers rely on
   *   this to map results back to their original items; items without a valid
   *   index cannot be reordered.
   * - relevance_score (or score): (float, optional) The relevance score the
   *   model assigned to the document.
   * Provider implementations should preserve these keys when normalizing the
   * raw API response.
   *
   * @param \Drupal\ai\OperationType\Rerank\ReRankInput $input
   *   The rerank input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Rerank\ReRankOutput
   *   The response.
   */
  public function rerank(ReRankInput $input, string $model_id, array $tags = []): ReRankOutput;

}
