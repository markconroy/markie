<?php

namespace Drupal\ai\OperationType\ExtractiveQuestionAnswering;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for extractive question answering output.
 */
class ExtractiveQuestionAnsweringOutput implements OutputInterface {

  /**
   * An array of ExtractiveQuestionAnsweringItem objects.
   *
   * @var \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem[]
   */
  private array $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem[] $normalized
   *   The extractive question answering items.
   * @param mixed $rawOutput
   *   The raw output from the AI provider.
   * @param mixed $metadata
   *   The metadata from the AI provider.
   */
  public function __construct(array $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns an array of ExtractiveQuestionAnsweringItem objects.
   *
   * @return \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem[]
   *   The extractive question answering items.
   */
  public function getNormalized(): array {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => array_map(function (ExtractiveQuestionAnsweringItem $item) {
        return [
          'answer' => $item->getAnswer(),
          'score' => $item->getScore(),
          'start' => $item->getStart(),
          'end' => $item->getEnd(),
        ];
      }, $this->normalized),
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
