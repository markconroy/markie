<?php

namespace Drupal\ai\OperationType\Chat\Tools;

use Drupal\Component\Serialization\Json;

/**
 * The function output.
 */
class ToolsFunctionOutput implements ToolsFunctionOutputInterface {

  /**
   * The function/tool id.
   *
   * @var string
   */
  private string $toolId;

  /**
   * The function name.
   *
   * @var string
   *   The name.
   */
  private string $name;

  /**
   * The input function.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface
   */
  private ToolsFunctionInputInterface $inputFunction;

  /**
   * The property arguments.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResultInterface[]
   *   The arguments.
   */
  private array $arguments = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(ToolsFunctionInputInterface $input, string $tool_id, array $arguments = []) {
    $this->setToolId($tool_id);
    $this->setInputFunction($input);
    $this->setName($input->getName());
    $this->addArguments($arguments);
  }

  /**
   * {@inheritDoc}
   */
  public function getToolId(): string {
    return $this->toolId;
  }

  /**
   * {@inheritDoc}
   */
  public function setToolId(string $tool_id) {
    $this->toolId = $tool_id;
  }

  /**
   * {@inheritDoc}
   */
  public function getInputFunction(): ToolsFunctionInputInterface {
    return $this->inputFunction;
  }

  /**
   * {@inheritDoc}
   */
  public function setInputFunction(ToolsFunctionInputInterface $input) {
    $this->inputFunction = $input;
  }

  /**
   * {@inheritDoc}
   */
  public function getArguments(): array {
    return $this->arguments;
  }

  /**
   * {@inheritDoc}
   */
  public function addArguments(array $arguments) {
    foreach ($arguments as $name => $value) {
      $property = $this->getInputPropertyByName($name);
      $argument = new ToolsPropertyResult($property, $value);
      $this->addArgument($argument);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function addArgument(ToolsPropertyResultInterface $argument) {
    $this->arguments[] = $argument;
  }

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritDoc}
   */
  public function setName(string $name) {
    $this->name = $name;
  }

  /**
   * {@inheritDoc}
   */
  public function getInputPropertyByName(string $name): ?ToolsPropertyInputInterface {
    return $this->inputFunction->getPropertyByName($name);
  }

  /**
   * {@inheritDoc}
   */
  public function getOutputRenderArray(): array {
    $output['id'] = $this->getToolId();
    $output['type'] = 'function';
    $args = [];
    foreach ($this->arguments as $argument) {
      $args[$argument->getName()] = $argument->getValue();
    }
    $output['function'] = [
      'name' => $this->getName(),
      'arguments' => Json::encode($args),
    ];
    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function validate() {
    // Validate all arguments.
    foreach ($this->arguments as $argument) {
      $argument->validate();
    }
    // Validate that the required arguments are present.
    $required = $this->inputFunction->getRequiredProperties();
    foreach ($required as $property) {
      $name = $property->getName();
      $found = FALSE;
      foreach ($this->arguments as $argument) {
        if ($argument->getName() === $name) {
          $found = TRUE;
          break;
        }
      }
      if (!$found) {
        throw new \Exception('Missing required argument: ' . $name);
      }
    }
  }

}
