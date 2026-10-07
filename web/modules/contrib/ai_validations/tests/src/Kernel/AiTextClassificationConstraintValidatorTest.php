<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiTextClassificationConstraint;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiTextClassificationConstraintValidator;
use Drupal\ai_validations_test\Plugin\AiProvider\ClassifierTestProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests the text classification validator's match/confidence/na logic.
 *
 * Drives the validator through the real AI provider manager, backed by the
 * ai_validations_test "classifier_test" provider whose returned
 * classifications are controlled via state.
 *
 * @see \Drupal\ai_validations\Plugin\Validation\Constraint\AiTextClassificationConstraintValidator
 */
#[Group('ai_validations')]
#[Group('3595515')]
class AiTextClassificationConstraintValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_test',
    'ai_validations',
    'ai_validations_test',
  ];

  /**
   * A resolved "provider__model" option pointing at the test provider.
   */
  protected const OPTION = 'classifier_test__gpt-test';

  /**
   * Sets the classifications the test provider should return.
   */
  protected function setClassifications(array $classifications): void {
    $this->container->get('state')
      ->set(ClassifierTestProvider::STATE_CLASSIFICATIONS, $classifications);
  }

  /**
   * Builds a validator initialized with the given execution context.
   */
  protected function buildValidator(ExecutionContextInterface $context): AiTextClassificationConstraintValidator {
    /** @var \Drupal\ai_validations\Plugin\Validation\Constraint\AiTextClassificationConstraintValidator $validator */
    $validator = AiTextClassificationConstraintValidator::create($this->container);
    $validator->initialize($context);
    return $validator;
  }

  /**
   * Builds a constraint with the given configuration overrides.
   */
  protected function buildConstraint(array $values = []): AiTextClassificationConstraint {
    $constraint = new AiTextClassificationConstraint();
    $constraint->model = $values['model'] ?? self::OPTION;
    $constraint->tag = $values['tag'] ?? 'positive';
    $constraint->finder = $values['finder'] ?? 'exact';
    $constraint->minimum = $values['minimum'] ?? 0.8;
    $constraint->message = $values['message'] ?? 'This value is not valid.';
    $constraint->na = $values['na'] ?? 'skip';
    return $constraint;
  }

  /**
   * A matching label above the confidence threshold raises a violation.
   */
  public function testViolationWhenMatchAboveThreshold(): void {
    $this->setClassifications([['label' => 'positive', 'confidence' => 0.9]]);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with('This value is not valid.', []);

    $this->buildValidator($context)->validate('some text', $this->buildConstraint());
  }

  /**
   * A matching label below the confidence threshold raises no violation.
   */
  public function testNoViolationWhenConfidenceBelowThreshold(): void {
    $this->setClassifications([['label' => 'positive', 'confidence' => 0.5]]);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    $this->buildValidator($context)->validate('some text', $this->buildConstraint());
  }

  /**
   * A non-matching label raises no violation, even at high confidence.
   */
  public function testNoViolationWhenLabelDoesNotMatch(): void {
    $this->setClassifications([['label' => 'negative', 'confidence' => 0.99]]);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    $this->buildValidator($context)->validate('some text', $this->buildConstraint());
  }

  /**
   * The "contains" finder matches a label that includes the tag.
   */
  public function testContainsFinderMatchesSubstringLabel(): void {
    $this->setClassifications([['label' => 'very positive', 'confidence' => 0.9]]);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())->method('addViolation');

    $this->buildValidator($context)
      ->validate('some text', $this->buildConstraint(['finder' => 'contains']));
  }

  /**
   * An empty field value short-circuits before any provider call.
   */
  public function testEmptyTextSkipsValidation(): void {
    $this->setClassifications([['label' => 'positive', 'confidence' => 0.9]]);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    $this->buildValidator($context)->validate('', $this->buildConstraint());
  }

  /**
   * A missing model with no configured default raises a violation.
   */
  public function testMissingModelRaisesViolation(): void {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with('No AI model specified to do validation', []);

    $this->buildValidator($context)
      ->validate('some text', $this->buildConstraint(['model' => '', 'na' => 'fail']));
  }

  /**
   * A provider failure raises a violation when na is "fail".
   */
  public function testProviderFailureRaisesViolationWhenNaFail(): void {
    $this->container->get('state')->set(ClassifierTestProvider::STATE_THROW, TRUE);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with('AI provider failed to classify text', []);

    $this->buildValidator($context)
      ->validate('some text', $this->buildConstraint(['na' => 'fail']));
  }

  /**
   * A provider failure is swallowed when na is "skip".
   */
  public function testProviderFailureIsSkippedWhenNaSkip(): void {
    $this->container->get('state')->set(ClassifierTestProvider::STATE_THROW, TRUE);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    $this->buildValidator($context)
      ->validate('some text', $this->buildConstraint(['na' => 'skip']));
  }

}
