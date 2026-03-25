<?php

declare(strict_types=1);

namespace Drupal\upgrade_status;

/**
 * A value object containing a deprecation message with some metadata.
 */
class DeprecationMessage {

  /**
   * The message.
   *
   * @var string
   */
  protected $message;

  /**
   * The line associated to the deprecation message.
   *
   * @var int
   */
  protected $line;

  /**
   * The file related to the deprecation message.
   *
   * @var string
   */
  protected $file;


  /**
   * The analyzer providing the message.
   *
   * @var string
   */
  protected string $analyzer;

  /**
   * Constructs a new deprecation message.
   *
   * @param string $message
   *   The message.
   * @param string $file
   *   The file related to the deprecation message.
   * @param int $line
   *   The line associated to the deprecation message.
   * @param string $analyzer
   *   The analyzer providing the message.
   */
  public function __construct(string $message, string $file = '', int $line = 0, string $analyzer = '') {
    $this->message = $message;
    $this->file = $file;
    $this->line = $line;
    $this->analyzer = $analyzer;
  }

  /**
   * Gets the message.
   *
   * @return string
   *   The deprecation message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Gets the file.
   *
   * @return string
   *   The file related to the deprecation message.
   */
  public function getFile(): string {
    return $this->file;
  }

  /**
   * Gets the line.
   *
   * @return int
   *   The line number associated with the deprecation message.
   */
  public function getLine(): int {
    return $this->line;
  }

  /**
   * Get analyzer providing the message.
   *
   * @return string
   *   The analyzer that generated the deprecation message.
   */
  public function getAnalyzer(): string {
    return $this->analyzer;
  }

}
