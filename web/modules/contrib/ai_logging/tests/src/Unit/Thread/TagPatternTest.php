<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Unit\Thread;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_logging\Thread\TagPattern;

/**
 * Tests matching request tags against a LIKE pattern.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Thread\TagPattern
 */
class TagPatternTest extends UnitTestCase {

  /**
   * Tests matching tags against a LIKE pattern.
   *
   * @covers ::matches
   */
  public function testMatches(): void {
    $tags = ['ai_agents', 'ai_agents_prompt_agent'];
    $this->assertTrue(TagPattern::matches($tags, 'ai_agents_prompt_%'));
    $this->assertTrue(TagPattern::matches($tags, 'AI_AGENTS'));
    $this->assertTrue(TagPattern::matches($tags, 'ai_agent_'));
    $this->assertTrue(TagPattern::matches($tags, '%%%prompt%%%'));
    $this->assertFalse(TagPattern::matches($tags, 'ai_agents_prompt'));
    // Only % and _ are wildcards; any other character matches itself.
    $this->assertFalse(TagPattern::matches(['ai-agents'], 'ai.agents'));
    $this->assertFalse(TagPattern::matches(['ai_agents'], 'ai_agents|.*'));
    $this->assertFalse(TagPattern::matches([], '%'));
  }

}
