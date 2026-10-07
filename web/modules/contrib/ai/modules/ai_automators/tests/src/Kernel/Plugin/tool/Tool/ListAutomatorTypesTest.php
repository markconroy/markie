<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\tool\Tool;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_automator:list_automator_types tool plugin.
 *
 * @group ai_automators
 * @covers \Drupal\ai_automators\Plugin\tool\Tool\ListAutomatorTypes
 */
#[RunTestsInSeparateProcesses]
class ListAutomatorTypesTest extends AutomatorToolTestBase {

  /**
   * The full catalog includes known types with id/label/field_rule/description.
   */
  public function testListAllTypesReturnsIdLabelDescription(): void {
    $tool = $this->toolManager->createInstance('ai_automator:list_automator_types');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $types = $tool->getOutputValue('types');
    $ids = array_column($types, 'id');
    $this->assertContains('llm_text_long', $ids);
    $this->assertContains('llm_boolean', $ids);

    $textLong = $types[array_search('llm_text_long', $ids, TRUE)];
    $this->assertSame('text_long', $textLong['field_rule']);
    $this->assertNotEmpty($textLong['label']);
  }

  /**
   * Inspecting one type returns full configuration detail.
   */
  public function testListSingleTypeReturnsFullDetail(): void {
    $tool = $this->toolManager->createInstance('ai_automator:list_automator_types');
    $tool->setInputValue('type', 'llm_text_long');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $type = $tool->getOutputValue('types');
    $this->assertSame('llm_text_long', $type['id']);
    $this->assertTrue($type['needs_prompt']);
    $this->assertIsBool($type['advanced_mode']);
    $this->assertIsArray($type['allowed_inputs']);
    $this->assertNotEmpty($type['placeholder_text']);
  }

  /**
   * Requesting an unknown type fails cleanly.
   */
  public function testUnknownTypeFails(): void {
    $tool = $this->toolManager->createInstance('ai_automator:list_automator_types');
    $tool->setInputValue('type', 'does_not_exist');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('Unknown automator type', (string) $result->getMessage());
  }

}
