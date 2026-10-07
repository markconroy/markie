<?php

namespace Drupal\Tests\ai\Unit\OperationType\ExtractiveQuestionAnswering;

use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the item functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem
 */
class ExtractiveQuestionAnsweringItemTest extends TestCase {

  /**
   * Test getting and setting for the item.
   */
  public function testGetSet(): void {
    $item = $this->getItem();
    $this->assertEquals('Paris', $item->getAnswer());
    $this->assertEquals(0.95, $item->getScore());
    $this->assertEquals(25, $item->getStart());
    $this->assertEquals(30, $item->getEnd());

    $item->setAnswer('London');
    $this->assertEquals('London', $item->getAnswer());

    $item->setScore(0.8);
    $this->assertEquals(0.8, $item->getScore());

    $item->setStart(10);
    $this->assertEquals(10, $item->getStart());

    $item->setEnd(16);
    $this->assertEquals(16, $item->getEnd());
  }

  /**
   * Test the score percentage.
   */
  public function testScorePercentage(): void {
    $item = $this->getItem();
    $this->assertEquals('95', $item->getScorePercentage());
  }

  /**
   * Test null score.
   */
  public function testNullScore(): void {
    $item = new ExtractiveQuestionAnsweringItem('unknown');
    $this->assertNull($item->getScore());
    $this->assertNull($item->getStart());
    $this->assertNull($item->getEnd());
    $this->assertEquals('0', $item->getScorePercentage());
  }

  /**
   * Helper function to get the item.
   *
   * @return \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringItem
   *   The item.
   */
  public function getItem(): ExtractiveQuestionAnsweringItem {
    return new ExtractiveQuestionAnsweringItem('Paris', 0.95, 25, 30);
  }

}
