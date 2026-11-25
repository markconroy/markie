<?php

namespace Drupal\ai\OperationType\ImageToVideo;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for image to video input.
 */
class ImageToVideoInput extends InputBase implements InputInterface {

  /**
   * The image file to animate.
   *
   * @var \Drupal\ai\OperationType\GenericType\ImageFile
   */
  private ImageFile $file;

  /**
   * An optional prompt to guide the generated video.
   *
   * @var string|null
   */
  private ?string $prompt = NULL;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file to animate.
   */
  public function __construct(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the image file to animate.
   *
   * @return \Drupal\ai\OperationType\GenericType\ImageFile
   *   The image file.
   */
  public function getImageFile(): ImageFile {
    return $this->file;
  }

  /**
   * Set the image file.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $file
   *   The image file.
   */
  public function setImageFile(ImageFile $file) {
    $this->file = $file;
  }

  /**
   * Get the prompt that guides the generated video.
   *
   * @return string|null
   *   The prompt, or NULL if not set.
   */
  public function getPrompt(): ?string {
    return $this->prompt;
  }

  /**
   * Set the prompt that guides the generated video.
   *
   * @param string|null $prompt
   *   The prompt to set, or NULL to unset.
   */
  public function setPrompt(?string $prompt): void {
    $this->prompt = $prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->file->getFilename();
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'file' => $this->file->toArray(),
      'prompt' => $this->prompt,
    ];
  }

}
