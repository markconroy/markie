<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Exception;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Exception\AiExceptionInterface;
use Drupal\ai\Exception\AiFunctionCallingExecutionError;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\Exception\AiToolsRecoverableExceptionInterface;
use Drupal\ai\Exception\AiToolsValidationException;

/**
 * Tests that tool validation failures can be fed back to the model.
 *
 * @coversDefaultClass \Drupal\ai\Exception\AiToolsValidationException
 * @group ai
 */
class AiToolsValidationExceptionTest extends UnitTestCase {

  /**
   * Tool runners catching execution errors must also get validation errors.
   *
   * Mirrors the per-tool catch in AiAgentEntityWrapper::determineSolvability(),
   * which converts the caught message into a tool role chat message.
   *
   * @return void
   *   Nothing.
   */
  public function testCaughtAsFunctionCallingExecutionError(): void {
    $output = NULL;
    try {
      throw new AiToolsValidationException('The field field_tags does not exist on the entity type node.');
    }
    catch (AiFunctionCallingExecutionError $exception) {
      $output = $exception->getMessage();
    }

    $this->assertSame('The field field_tags does not exist on the entity type node.', $output);
  }

  /**
   * Tests that recoverable tool failures carry the marker interface.
   *
   * @param string $class
   *   The exception class to check.
   *
   * @dataProvider recoverableExceptionProvider
   *
   * @return void
   *   Nothing.
   */
  public function testRecoverableExceptions(string $class): void {
    $exception = new $class('Something the model can fix.');

    $this->assertInstanceOf(AiToolsRecoverableExceptionInterface::class, $exception);
    $this->assertInstanceOf(AiExceptionInterface::class, $exception);
  }

  /**
   * Provides the exceptions that should be handed back to the model.
   *
   * @return array
   *   Exception class names.
   */
  public static function recoverableExceptionProvider(): array {
    return [
      [AiFunctionCallingExecutionError::class],
      [AiToolsValidationException::class],
    ];
  }

  /**
   * Tests that fatal failures keep failing loudly.
   *
   * Retrying these only burns requests, so a tool runner must not swallow them
   * into tool output.
   *
   * @param string $class
   *   The exception class to check.
   *
   * @dataProvider fatalExceptionProvider
   *
   * @return void
   *   Nothing.
   */
  public function testFatalExceptionsAreNotRecoverable(string $class): void {
    $exception = new $class('Something the model cannot fix.');

    $this->assertNotInstanceOf(AiToolsRecoverableExceptionInterface::class, $exception);
    $this->assertNotInstanceOf(AiFunctionCallingExecutionError::class, $exception);
    $this->assertInstanceOf(AiExceptionInterface::class, $exception);
  }

  /**
   * Provides the exceptions that must abort the run.
   *
   * @return array
   *   Exception class names.
   */
  public static function fatalExceptionProvider(): array {
    return [
      [AiRateLimitException::class],
      [AiRequestErrorException::class],
      [AiQuotaException::class],
      [AiSetupFailureException::class],
    ];
  }

}
