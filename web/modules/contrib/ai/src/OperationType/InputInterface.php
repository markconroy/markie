<?php

namespace Drupal\ai\OperationType;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;

/**
 * Base Input Interface class.
 */
interface InputInterface {

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function toString(): string;

  /**
   * Returns all debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData(): array;

  /**
   * Set the debug data.
   *
   * @param array $debugData
   *   The debug data.
   */
  public function setDebugData(array $debugData): void;

  /**
   * Set one debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugDataValue(string $key, $value): void;

  /**
   * Set the guardrail set for this input.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailSetInterface $guardrails
   *   The guardrail set to set.
   */
  public function setGuardrailSet(AiGuardrailSetInterface $guardrails): void;

  /**
   * Get the guardrail set for this input.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailSetInterface|null
   *   The guardrail set for the chat, or NULL if not set.
   */
  public function getGuardrailSet(): ?AiGuardrailSetInterface;

  /**
   * Take note of an applied guardrail result.
   *
   * @param \Drupal\ai\Guardrail\Result\GuardrailResultInterface $guardrailResult
   *   An applied guardrail result.
   * @param \Drupal\ai\Entity\AiGuardrailModeEnum $mode
   *   The guardrail mode.
   */
  public function addGuardrailResult(GuardrailResultInterface $guardrailResult, AiGuardrailModeEnum $mode): void;

  /**
   * Get all applied guardrails results.
   *
   * @return \Drupal\ai\Guardrail\Result\GuardrailResultInterface[]
   *   The applied guardrails results.
   */
  public function getGuardrailsResults(): array;

  /**
   * Returns the input as an array.
   *
   * @return array
   *   The input as an array.
   */
  public function toArray(): array;

}
