<?php

namespace Drupal\Tests\ai\Unit\OperationType\ExtractiveQuestionAnswering;

use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput
 */
class ExtractiveQuestionAnsweringInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('What is the capital of France?', $input->getQuestion());
    $this->assertEquals('The capital of France is Paris.', $input->getContext());

    $input->setQuestion('Who founded Drupal?');
    $this->assertEquals('Who founded Drupal?', $input->getQuestion());

    $input->setContext('Drupal was founded by Dries Buytaert.');
    $this->assertEquals('Drupal was founded by Dries Buytaert.', $input->getContext());
  }

  /**
   * Test the toString method.
   */
  public function testToString(): void {
    $input = $this->getInput();
    $this->assertEquals('What is the capital of France?', $input->toString());
    $this->assertEquals('What is the capital of France?', (string) $input);
  }

  /**
   * Test the toArray method.
   */
  public function testToArray(): void {
    $input = $this->getInput();
    $array = $input->toArray();
    $this->assertIsArray($array);
    $this->assertArrayHasKey('question', $array);
    $this->assertArrayHasKey('context', $array);
    $this->assertEquals('What is the capital of France?', $array['question']);
    $this->assertEquals('The capital of France is Paris.', $array['context']);
  }

  /**
   * Helper function to get the input.
   *
   * @return \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput
   *   The input.
   */
  public function getInput(): ExtractiveQuestionAnsweringInput {
    return new ExtractiveQuestionAnsweringInput('What is the capital of France?', 'The capital of France is Paris.');
  }

}
