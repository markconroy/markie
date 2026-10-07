<?php

namespace Drupal\ai\OperationType\ExtractiveQuestionAnswering;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for extractive question answering input.
 */
class ExtractiveQuestionAnsweringInput extends InputBase implements InputInterface {

  /**
   * The question to answer.
   *
   * @var string
   */
  private string $question;

  /**
   * The context passage to extract the answer from.
   *
   * @var string
   */
  private string $context;

  /**
   * The constructor.
   *
   * @param string $question
   *   The question to answer.
   * @param string $context
   *   The context passage to extract the answer from.
   */
  public function __construct(string $question, string $context) {
    $this->question = $question;
    $this->context = $context;
  }

  /**
   * Get the question.
   *
   * @return string
   *   The question.
   */
  public function getQuestion(): string {
    return $this->question;
  }

  /**
   * Set the question.
   *
   * @param string $question
   *   The question.
   */
  public function setQuestion(string $question): void {
    $this->question = $question;
  }

  /**
   * Get the context passage.
   *
   * @return string
   *   The context passage.
   */
  public function getContext(): string {
    return $this->context;
  }

  /**
   * Set the context passage.
   *
   * @param string $context
   *   The context passage.
   */
  public function setContext(string $context): void {
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->question;
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
      'question' => $this->question,
      'context' => $this->context,
    ];
  }

}
