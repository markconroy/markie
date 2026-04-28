<?php

namespace Drupal\ai\OperationType;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;

/**
 * Base Input Interface class.
 */
abstract class InputBase implements InputInterface {

  /**
   * The debug data.
   *
   * @var array
   */
  private array $debugData = [];

  /**
   * The guardrails set that will be applied to this input.
   *
   * @var \Drupal\ai\Guardrail\AiGuardrailSetInterface|null
   */
  private ?AiGuardrailSetInterface $guardrails = NULL;

  /**
   * {@inheritdoc}
   */
  public function getDebugData(): array {
    return $this->debugData;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugData(array $debug_data): void {
    $this->debugData = $debug_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugDataValue(string $key, $value): void {
    $this->debugData[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setGuardrailSet(AiGuardrailSetInterface $guardrails): void {
    $this->guardrails = $guardrails;
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailSet(): ?AiGuardrailSetInterface {
    return $this->guardrails;
  }

  /**
   * {@inheritdoc}
   */
  public function addGuardrailResult(GuardrailResultInterface $guardrailResult, AiGuardrailModeEnum $mode): void {
    $applied_guardrails = $this->getDebugData()['applied_guardrails'] ?? [];
    $applied_guardrails[$mode->value] = $guardrailResult;
    $this->setDebugDataValue('applied_guardrails', $applied_guardrails);
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailsResults(): array {
    return $this->getDebugData()['applied_guardrails'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'debug_data' => $this->getDebugData(),
    ];
  }

}
