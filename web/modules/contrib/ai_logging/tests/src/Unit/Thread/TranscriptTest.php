<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Unit\Thread;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_logging\Thread\Transcript;

/**
 * Tests reading logged chat transcripts.
 *
 * @coversDefaultClass \Drupal\ai_logging\Thread\Transcript
 * @group ai_logging
 */
class TranscriptTest extends UnitTestCase {

  /**
   * Tests finding the last user message.
   *
   * @covers ::lastUserMessage
   */
  public function testLastUserMessage(): void {
    $this->assertSame('A question?', Transcript::lastUserMessage("user\nA question?\n"));
    // An agent's later turns keep the question, followed by tool use.
    $agent = implode("\n", [
      'user',
      'First question?',
      'assistant',
      'An answer.',
      'user',
      'A follow-up',
      'on two lines?',
      'assistant',
      '[calls search] {"query":"x"}',
      'tool (search)',
      'A result.',
      '',
    ]);
    $this->assertSame("A follow-up\non two lines?", Transcript::lastUserMessage($agent));
    // A tool result never counts as a user message.
    $this->assertNull(Transcript::lastUserMessage("assistant\nHello.\ntool (user)\nuser data\n"));
    // Nor does a prompt that isn't a transcript, or an empty message.
    $this->assertNull(Transcript::lastUserMessage('Summarize this text.'));
    $this->assertNull(Transcript::lastUserMessage("user\n\nassistant\nHi.\n"));
  }

}
