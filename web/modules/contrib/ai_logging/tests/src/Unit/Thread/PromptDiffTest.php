<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Unit\Thread;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_logging\Thread\PromptDiff;

/**
 * Tests finding what a call's prompt added to the previous one.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Thread\PromptDiff
 */
class PromptDiffTest extends UnitTestCase {

  /**
   * Tests that a continued prompt gives only the added lines.
   *
   * @covers ::newText
   */
  public function testContinuation(): void {
    $previous = "user\nWhat happened?\n";
    $current = $previous . "assistant\n[calls search] {\"query\":\"x\"}\ntool (search)\nResults\n";
    $this->assertSame("assistant\n[calls search] {\"query\":\"x\"}\ntool (search)\nResults\n", PromptDiff::newText($previous, $current));
  }

  /**
   * Tests that the added text starts at the beginning of its line.
   *
   * @covers ::newText
   */
  public function testStartsAtLineStart(): void {
    $shared = str_repeat("line of shared history\n", 20);
    $this->assertSame("tool (read)\nnew text\n", PromptDiff::newText($shared . "tool (search)\nold\n", $shared . "tool (read)\nnew text\n"));
  }

  /**
   * Tests that unrelated or identical prompts give no difference.
   *
   * @covers ::newText
   */
  public function testNoDifference(): void {
    // Too little in common to be the same conversation.
    $this->assertNull(PromptDiff::newText("user\nOne question\n", "user\nAnother question\n"));
    // Nothing added.
    $prompt = str_repeat('x', 300);
    $this->assertNull(PromptDiff::newText($prompt, $prompt));
  }

  /**
   * Tests that a long shared start is found past the first block.
   *
   * @covers ::newText
   */
  public function testLongPrompts(): void {
    $shared = str_repeat("history line\n", 1000);
    $this->assertSame("added\n", PromptDiff::newText($shared, $shared . "added\n"));
    $this->assertSame("second\n", PromptDiff::newText($shared . "first\n", $shared . "second\n"));
  }

}
