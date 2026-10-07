<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\ExtractiveQuestionAnswering;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput;
use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem;
use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringOutput;

/**
 * This tests the Extractive Question Answering calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInterface
 *
 * @group ai
 */
class ExtractiveQuestionAnsweringInterfaceTest extends KernelTestBase {

  /**
   * Model for the setup.
   *
   * @var string
   */
  protected $model;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * Test the extractive question answering with normal input.
   */
  public function testExtractiveQuestionAnsweringNormal(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ExtractiveQuestionAnsweringInput(
      'What is the capital of France?',
      'The capital of France is Paris.'
    );

    $result = $provider->extractiveQuestionAnswering($input, 'test');
    // Should be an ExtractiveQuestionAnsweringOutput object.
    $this->assertInstanceOf(ExtractiveQuestionAnsweringOutput::class, $result);

    $normalized = $result->getNormalized();
    // Normalized output should be an array.
    $this->assertIsArray($normalized);
    // The array should have at least 1 element.
    $this->assertNotEmpty($normalized);
    // The first object should be an ExtractiveQuestionAnsweringItem object.
    $this->assertInstanceOf(ExtractiveQuestionAnsweringItem::class, $normalized[0]);
    // The answer should be a non-empty string.
    $this->assertNotEmpty($normalized[0]->getAnswer());
    // The score should be a float.
    $this->assertIsFloat($normalized[0]->getScore());
    // The start should be an integer.
    $this->assertIsInt($normalized[0]->getStart());
    // The end should be an integer.
    $this->assertIsInt($normalized[0]->getEnd());
  }

  /**
   * Test the extractive question answering without a model.
   */
  public function testExtractiveQuestionAnsweringBroken(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ExtractiveQuestionAnsweringInput(
      'What is the capital of France?',
      'The capital of France is Paris.'
    );
    $this->expectException(AiBadRequestException::class);
    $provider->extractiveQuestionAnswering($input, $this->model);
  }

}
