<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Plugin\Validation\Constraint;

use Drupal\ai\Plugin\Validation\Constraint\ProviderModelDependencyConstraint;
use Drupal\ai\Plugin\Validation\Constraint\ProviderModelDependencyConstraintValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @coversDefaultClass \Drupal\ai\Plugin\Validation\Constraint\ProviderModelDependencyConstraintValidator
 * @group ai
 */
class ProviderModelDependencyConstraintValidatorTest extends TestCase {

  /**
   * The validator instance.
   *
   * @var \Drupal\ai\Plugin\Validation\Constraint\ProviderModelDependencyConstraintValidator
   */
  protected $validator;

  /**
   * The constraint instance.
   *
   * @var \Drupal\ai\Plugin\Validation\Constraint\ProviderModelDependencyConstraint
   */
  protected $constraint;

  /**
   * The execution context mock.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $context;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->validator = new ProviderModelDependencyConstraintValidator();
    $this->constraint = new ProviderModelDependencyConstraint();
    $this->context = $this->createMock(ExecutionContextInterface::class);

    // Use reflection to set the protected context property.
    $reflection = new \ReflectionClass($this->validator);
    $property = $reflection->getProperty('context');
    $property->setAccessible(TRUE);
    $property->setValue($this->validator, $this->context);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithBothFieldsSet(): void {
    $value = [
      'provider_id' => 'openai',
      'model_id' => 'gpt-4',
    ];

    $this->context->expects($this->never())->method('addViolation');
    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithNeitherFieldSet(): void {
    $value = [
      'use_default' => TRUE,
    ];

    $this->context->expects($this->never())->method('addViolation');
    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithEmptyArray(): void {
    $value = [];

    $this->context->expects($this->never())->method('addViolation');
    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithModelIdButNoProviderId(): void {
    $value = [
      'model_id' => 'gpt-4',
    ];

    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->message);

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithProviderIdButNoModelId(): void {
    $value = [
      'provider_id' => 'openai',
    ];

    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->message);

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithEmptyStringValues(): void {
    $value = [
      'provider_id' => '',
      'model_id' => '',
    ];

    $this->context->expects($this->never())->method('addViolation');
    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithNullValue(): void {
    $this->context->expects($this->never())->method('addViolation');
    $this->validator->validate(NULL, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithNonArrayValue(): void {
    $this->context->expects($this->never())->method('addViolation');
    $this->validator->validate('string', $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithEmptyModelIdButSetProviderId(): void {
    $value = [
      'provider_id' => 'openai',
      'model_id' => '',
    ];

    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->message);

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithEmptyProviderIdButSetModelId(): void {
    $value = [
      'provider_id' => '',
      'model_id' => 'gpt-4',
    ];

    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->message);

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithNullProviderIdButSetModelId(): void {
    $value = [
      'provider_id' => NULL,
      'model_id' => 'gpt-4',
    ];

    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->message);

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateWithNullModelIdButSetProviderId(): void {
    $value = [
      'provider_id' => 'openai',
      'model_id' => NULL,
    ];

    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->message);

    $this->validator->validate($value, $this->constraint);
  }

}
