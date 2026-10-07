<?php

namespace Drupal\ai\OperationType\ExtractiveQuestionAnswering;

/**
 * One extractive question answering result item.
 */
class ExtractiveQuestionAnsweringItem {

  /**
   * The extracted answer text.
   *
   * @var string
   */
  private string $answer;

  /**
   * The confidence score of the answer.
   *
   * @var float|null
   */
  private float|NULL $score;

  /**
   * The start character position of the answer in the context.
   *
   * @var int|null
   */
  private int|NULL $start;

  /**
   * The end character position of the answer in the context.
   *
   * @var int|null
   */
  private int|NULL $end;

  /**
   * The constructor.
   *
   * @param string $answer
   *   The extracted answer text.
   * @param float|null $score
   *   The confidence score.
   * @param int|null $start
   *   The start character position in the context.
   * @param int|null $end
   *   The end character position in the context.
   */
  public function __construct(string $answer, float|NULL $score = NULL, int|NULL $start = NULL, int|NULL $end = NULL) {
    $this->answer = $answer;
    $this->score = $score;
    $this->start = $start;
    $this->end = $end;
  }

  /**
   * Returns the extracted answer text.
   *
   * @return string
   *   The answer.
   */
  public function getAnswer(): string {
    return $this->answer;
  }

  /**
   * Sets the extracted answer text.
   *
   * @param string $answer
   *   The answer.
   */
  public function setAnswer(string $answer): void {
    $this->answer = $answer;
  }

  /**
   * Returns the confidence score.
   *
   * @return float|null
   *   The confidence score.
   */
  public function getScore(): float|NULL {
    return $this->score;
  }

  /**
   * Sets the confidence score.
   *
   * @param float|null $score
   *   The confidence score.
   */
  public function setScore(float|NULL $score): void {
    $this->score = $score;
  }

  /**
   * Returns the start character position of the answer in the context.
   *
   * @return int|null
   *   The start position.
   */
  public function getStart(): int|NULL {
    return $this->start;
  }

  /**
   * Sets the start character position.
   *
   * @param int|null $start
   *   The start position.
   */
  public function setStart(int|NULL $start): void {
    $this->start = $start;
  }

  /**
   * Returns the end character position of the answer in the context.
   *
   * @return int|null
   *   The end position.
   */
  public function getEnd(): int|NULL {
    return $this->end;
  }

  /**
   * Sets the end character position.
   *
   * @param int|null $end
   *   The end position.
   */
  public function setEnd(int|NULL $end): void {
    $this->end = $end;
  }

  /**
   * Returns the confidence score as a percentage.
   *
   * @return string
   *   The confidence score as a percentage.
   */
  public function getScorePercentage(): string {
    return $this->score ? round($this->score * 100, 2) : '0';
  }

}
