<?php

namespace Drupal\ai_test\OperationType\Echo;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for echo operations.
 */
class EchoInput implements InputInterface {

  /**
   * The constructor.
   *
   * @param string $input
   *   The echo input.
   */
  public function __construct(private string $input) {}

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->input;
  }

  /**
   * {@inheritdoc}
   */
  public function getDebugData(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugData(array $debugData): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugDataValue(string $key, $value): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function setGuardrailSet(AiGuardrailSetInterface $guardrails): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailSet(): ?AiGuardrailSetInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function addGuardrailResult(GuardrailResultInterface $guardrailResult, AiGuardrailModeEnum $mode): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailsResults(): array {
    return [];
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
      'input' => $this->input,
    ];
  }

}
