<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\tool\Tool;

use Drupal\ai_automators\Entity\AiAutomator;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_automator:list_automators and ai_automator:get_automator tools.
 *
 * @group ai_automators
 * @covers \Drupal\ai_automators\Plugin\tool\Tool\ListAutomators
 * @covers \Drupal\ai_automators\Plugin\tool\Tool\GetAutomator
 */
#[RunTestsInSeparateProcesses]
class ListAndGetAutomatorTest extends AutomatorToolTestBase {

  /**
   * Creates a persisted ai_automator fixture entity.
   */
  protected function createAutomator(): AiAutomator {
    $automator = AiAutomator::create([
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
      'plugin_config' => [
        'automator_rule' => 'llm_text_long',
        'automator_ai_provider' => 'echoai',
      ],
    ]);
    $automator->save();
    return $automator;
  }

  /**
   * Listing automators returns compact summaries.
   */
  public function testListAutomatorsReturnsSummaries(): void {
    $this->createAutomator();

    $tool = $this->toolManager->createInstance('ai_automator:list_automators');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $automators = $tool->getOutputValue('automators');
    $this->assertCount(1, $automators);
    $this->assertSame('node.article.body.default', $automators[0]['id']);
    $this->assertSame('llm_text_long', $automators[0]['rule']);
    $this->assertArrayNotHasKey('prompt', $automators[0]);
  }

  /**
   * Listing automators filtered by entity_type/bundle/field_name.
   */
  public function testListAutomatorsFiltersByEntityTypeBundleField(): void {
    $this->createAutomator();

    $tool = $this->toolManager->createInstance('ai_automator:list_automators');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'page');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $this->assertCount(0, $tool->getOutputValue('automators'));
  }

  /**
   * Getting an automator returns full detail including plugin_config.
   */
  public function testGetAutomatorReturnsFullDetailIncludingPluginConfig(): void {
    $this->createAutomator();

    $tool = $this->toolManager->createInstance('ai_automator:get_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());
    $this->assertSame('{{ context }}', $tool->getOutputValue('prompt'));
    $pluginConfig = $tool->getOutputValue('plugin_config');
    $this->assertSame('echoai', $pluginConfig['automator_ai_provider']);
  }

  /**
   * Getting a missing automator fails cleanly.
   */
  public function testGetAutomatorMissingFails(): void {
    $tool = $this->toolManager->createInstance('ai_automator:get_automator');
    $tool->setInputValue('automator_id', 'node.article.body.default');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('not found', (string) $result->getMessage());
  }

}
