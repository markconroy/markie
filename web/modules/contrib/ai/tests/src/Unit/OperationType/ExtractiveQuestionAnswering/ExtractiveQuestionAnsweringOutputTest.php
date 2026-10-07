<?php

namespace Drupal\Tests\ai\Unit\OperationType\ExtractiveQuestionAnswering;

use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem;
use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringOutput
 */
class ExtractiveQuestionAnsweringOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $normalized = $output->getNormalized();
    $this->assertCount(1, $normalized);
    $this->assertEquals('Paris', $normalized[0]->getAnswer());
    $this->assertEquals(0.95, $normalized[0]->getScore());
    $this->assertEquals(25, $normalized[0]->getStart());
    $this->assertEquals(30, $normalized[0]->getEnd());
  }

  /**
   * Test the toArray method.
   */
  public function testToArray(): void {
    $output = $this->getOutput();
    $array = $output->toArray();
    $this->assertIsArray($array);
    $this->assertArrayHasKey('normalized', $array);
    $this->assertArrayHasKey('rawOutput', $array);
    $this->assertArrayHasKey('metadata', $array);
    $this->assertCount(1, $array['normalized']);
    $this->assertEquals('Paris', $array['normalized'][0]['answer']);
    $this->assertEquals(0.95, $array['normalized'][0]['score']);
    $this->assertEquals(25, $array['normalized'][0]['start']);
    $this->assertEquals(30, $array['normalized'][0]['end']);
  }

  /**
   * Helper function to get the output.
   *
   * @return \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringOutput
   *   The output.
   */
  public function getOutput(): ExtractiveQuestionAnsweringOutput {
    $item = new ExtractiveQuestionAnsweringItem('Paris', 0.95, 25, 30);
    return new ExtractiveQuestionAnsweringOutput([$item], [
      [
        'answer' => 'Paris',
        'score' => 0.95,
        'start' => 25,
        'end' => 30,
      ],
    ], []);
  }

}
