<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for collection of embeddings input.
 */
class EmbeddingsCollectionInput extends InputBase implements InputInterface {

  /**
   * The prompts to convert to vectors.
   *
   * A list of strings; providers that support multi embeddings process them
   * in a single API call, in the same order.
   *
   * @var array<string>
   */
  private array $prompts;

  /**
   * The constructor.
   *
   * @param array $prompts
   *   The prompts to convert to vectors.
   */
  public function __construct(array $prompts = []) {
    $this->prompts = $prompts;
  }

  /**
   * Get the number of prompts.
   *
   * @return int
   *   The number of prompts (1 for single prompt, count for collection).
   */
  public function getPromptCount(): int {
    return count($this->prompts);
  }

  /**
   * Gets the prompts.
   *
   * @return array|string[]
   *   The prompts to convert to vectors.
   */
  public function getPrompts(): array {
    return $this->prompts;
  }

  /**
   * Set the prompts.
   *
   * @param array $prompts
   *   The prompt.
   */
  public function setPrompts(array $prompts): void {
    $this->prompts = $prompts;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return implode("\n", $this->prompts);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'prompts' => $this->prompts,
    ];
  }

}
