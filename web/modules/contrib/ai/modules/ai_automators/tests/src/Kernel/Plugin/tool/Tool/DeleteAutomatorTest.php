<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\tool\Tool;

use Drupal\ai_automators\Entity\AiAutomator;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_automator:delete_automator tool plugin.
 *
 * @group ai_automators
 * @covers \Drupal\ai_automators\Plugin\tool\Tool\DeleteAutomator
 */
#[RunTestsInSeparateProcesses]
class DeleteAutomatorTest extends AutomatorToolTestBase {

  /**
   * Deleting an automator removes the entity.
   */
  public function testDeleteRemovesEntity(): void {
    AiAutomator::create([
      'id' => 'node.article.body.default',
      'label' => 'Body Default',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'body',
      'rule' => 'llm_text_long',
      'input_mode' => 'base',
      'weight' => 100,
      'worker_type' => 'direct',
      'edit_mode' => FALSE,
      'base_field' => 'title',
      'prompt' => '{{ context }}',
      'token' => '',
      'plugin_config' => [],
    ])->save();

    $tool = $this->toolManager->createInstance('ai_automator:delete_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $this->assertSame('node.article.body.default', $tool->getOutputValue('deleted_id'));
    $this->assertNull(AiAutomator::load('node.article.body.default'));
  }

  /**
   * Deleting a missing automator fails cleanly.
   */
  public function testDeleteMissingAutomatorFails(): void {
    $tool = $this->toolManager->createInstance('ai_automator:delete_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('not found', (string) $result->getMessage());
  }

  /**
   * Omitting all identification inputs fails cleanly.
   */
  public function testDeleteWithoutIdentificationFails(): void {
    $tool = $this->toolManager->createInstance('ai_automator:delete_automator');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('Provide either automator_id', (string) $result->getMessage());
  }

}
