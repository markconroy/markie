<?php

namespace Drupal\ai\OperationType\Moderation;

/**
 * Complex object for the moderation response.
 */
class ModerationResponse {

  /**
   * The answer for the moderation. True is flagged.
   *
   * @var bool
   */
  private bool $flagged;

  /**
   * The verbose information.
   *
   * @var array
   */
  private array $information;

  /**
   * The constructor.
   *
   * @param bool $flagged
   *   The flagged status.
   * @param array $information
   *   The verbose information if any.
   */
  public function __construct(string $flagged, array $information = []) {
    $this->flagged = $flagged;
    $this->information = $information;
  }

  /**
   * Get the role of the message.
   *
   * @return string
   *   The role.
   */
  public function isFlagged(): string {
    return $this->flagged;
  }

  /**
   * Get the information.
   *
   * @return array
   *   The information.
   */
  public function getInformation(): array {
    return $this->information;
  }

  /**
   * Set if the message is flagged.
   *
   * @param bool $flagged
   *   The flagged status.
   */
  public function setFlagged(bool $flagged): void {
    $this->flagged = $flagged;
  }

  /**
   * Set the information.
   *
   * @param array $information
   *   The information.
   */
  public function setInformation(array $information): void {
    $this->information = $information;
  }

}
