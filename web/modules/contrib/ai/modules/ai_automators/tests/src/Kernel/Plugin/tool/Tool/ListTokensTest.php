<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\tool\Tool;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_automator:list_tokens tool plugin.
 *
 * @group ai_automators
 * @covers \Drupal\ai_automators\Plugin\tool\Tool\ListTokens
 */
#[RunTestsInSeparateProcesses]
class ListTokensTest extends AutomatorToolTestBase {

  /**
   * Base mode returns the rule's default Twig placeholders.
   */
  public function testBaseModeTokensReturnsRuleTokenMap(): void {
    $tool = $this->toolManager->createInstance('ai_automator:list_tokens');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->setInputValue('rule', 'llm_text_long');
    $tool->setInputValue('mode', 'base');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $tokens = $tool->getOutputValue('tokens');
    $this->assertArrayHasKey('context', $tokens);
    $this->assertArrayHasKey('raw_context', $tokens);
    $this->assertArrayHasKey('max_amount', $tokens);
  }

  /**
   * Token mode returns core Token module placeholders for the entity type.
   */
  public function testTokenModeTokensReturnsCoreTokenInfoFilteredByEntityType(): void {
    $tool = $this->toolManager->createInstance('ai_automator:list_tokens');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->setInputValue('mode', 'token');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $tokens = $tool->getOutputValue('tokens');
    $this->assertNotEmpty($tokens);
    $this->assertSame('node', $tool->getOutputValue('token_type'));
  }

  /**
   * Base mode without a rule and no existing automator fails cleanly.
   */
  public function testMissingRuleWithNoExistingAutomatorFails(): void {
    $tool = $this->toolManager->createInstance('ai_automator:list_tokens');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->setInputValue('mode', 'base');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('rule is required', (string) $result->getMessage());
  }

}
