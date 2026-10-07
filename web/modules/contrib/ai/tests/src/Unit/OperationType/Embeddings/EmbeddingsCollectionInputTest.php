<?php

namespace Drupal\Tests\ai\Unit\OperationType\Embeddings;

use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests the multi embeddings input value object.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInput
 */
class EmbeddingsCollectionInputTest extends TestCase {

  /**
   * Test getting, setting and counting prompts.
   */
  public function testGetSet(): void {
    $input = new EmbeddingsCollectionInput(['one', 'two', 'three']);
    $this->assertSame(['one', 'two', 'three'], $input->getPrompts());
    $this->assertSame(3, $input->getPromptCount());

    $input->setPrompts(['a', 'b']);
    $this->assertSame(['a', 'b'], $input->getPrompts());
    $this->assertSame(2, $input->getPromptCount());
  }

  /**
   * Test the empty default and count.
   */
  public function testEmptyDefault(): void {
    $input = new EmbeddingsCollectionInput();
    $this->assertSame([], $input->getPrompts());
    $this->assertSame(0, $input->getPromptCount());
  }

  /**
   * Test the string and array representations.
   */
  public function testStringAndArray(): void {
    $input = new EmbeddingsCollectionInput(['first', 'second']);
    $this->assertSame("first\nsecond", $input->toString());
    $this->assertSame("first\nsecond", (string) $input);
    $this->assertSame(['prompts' => ['first', 'second']], $input->toArray());
  }

}
