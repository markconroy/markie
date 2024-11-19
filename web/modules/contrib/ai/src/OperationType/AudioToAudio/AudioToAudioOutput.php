<?php

namespace Drupal\ai\OperationType\AudioToAudio;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for audio to audio output.
 */
class AudioToAudioOutput implements OutputInterface {

  /**
   * The audio file.
   *
   * @var \Drupal\ai\OperationType\GenericType\AudioFile[]
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
   * @param \Drupal\ai\OperationType\GenericType\AudioFile[] $normalized
   *   The audio file.
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
   * Returns a audio file object.
   *
   * @return \Drupal\ai\OperationType\GenericType\AudioFile[]
   *   The audio file object.
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
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
