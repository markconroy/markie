<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\tool\Tool;

use Drupal\ai_automators\Entity\AiAutomator;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_automator:save_automator tool plugin.
 *
 * @group ai_automators
 * @covers \Drupal\ai_automators\Plugin\tool\Tool\SaveAutomator
 */
#[RunTestsInSeparateProcesses]
class SaveAutomatorTest extends AutomatorToolTestBase {

  /**
   * Creating an automator persists the computed ID and mirrored plugin_config.
   */
  public function testCreateAutomatorPersistsExpectedValuesAndPluginConfig(): void {
    $tool = $this->toolManager->createInstance('ai_automator:save_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->setInputValue('rule', 'llm_text_long');
    $tool->setInputValue('base_field', 'title');
    $tool->setInputValue('prompt', '{{ context }}');
    $tool->setInputValue('plugin_config_extra', json_encode([
      'automator_ai_provider' => 'echoai',
      'automator_ai_model' => 'default',
    ]));
    $tool->execute();

    $result = $tool->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $entity = AiAutomator::load('node.article.body.default');
    $this->assertNotNull($entity);
    $this->assertSame('llm_text_long', $entity->get('rule'));
    $this->assertSame('base', $entity->get('input_mode'));
    $this->assertSame(100, $entity->get('weight'));
    $this->assertSame('direct', $entity->get('worker_type'));
    $this->assertSame('title', $entity->get('base_field'));
    $this->assertSame('{{ context }}', $entity->get('prompt'));
    $this->assertTrue($entity->status());

    $pluginConfig = $entity->get('plugin_config');
    $this->assertSame('llm_text_long', $pluginConfig['automator_rule']);
    $this->assertSame('{{ context }}', $pluginConfig['automator_prompt']);
    $this->assertSame('title', $pluginConfig['automator_base_field']);
    $this->assertSame('echoai', $pluginConfig['automator_ai_provider']);
    $this->assertSame('default', $pluginConfig['automator_ai_model']);

    $this->assertSame('node.article.body.default', $tool->getOutputValue('id'));
  }

  /**
   * Updating an automator preserves plugin_config keys it did not touch.
   *
   * This is the critical regression to guard: a caller changing only the
   * prompt must not wipe out unrelated settings like the AI provider.
   */
  public function testUpdateAutomatorPreservesUnrelatedPluginConfigKeys(): void {
    $create = $this->toolManager->createInstance('ai_automator:save_automator');
    $create->setInputValue('entity_type', 'node');
    $create->setInputValue('bundle', 'article');
    $create->setInputValue('field_name', 'body');
    $create->setInputValue('rule', 'llm_text_long');
    $create->setInputValue('base_field', 'title');
    $create->setInputValue('prompt', 'Original prompt: {{ context }}');
    $create->setInputValue('plugin_config_extra', json_encode([
      'automator_ai_provider' => 'echoai',
      'automator_ai_model' => 'default',
    ]));
    $create->execute();
    $this->assertTrue($create->getResult()->isSuccess(), (string) $create->getResult()->getMessage());

    $update = $this->toolManager->createInstance('ai_automator:save_automator');
    $update->setInputValue('entity_type', 'node');
    $update->setInputValue('bundle', 'article');
    $update->setInputValue('field_name', 'body');
    $update->setInputValue('prompt', 'Updated prompt: {{ context }}');
    $update->execute();

    $result = $update->getResult();
    $this->assertTrue($result->isSuccess(), (string) $result->getMessage());

    $entity = AiAutomator::load('node.article.body.default');
    $this->assertSame('Updated prompt: {{ context }}', $entity->get('prompt'));

    $pluginConfig = $entity->get('plugin_config');
    $this->assertSame('Updated prompt: {{ context }}', $pluginConfig['automator_prompt']);
    // Unrelated keys from the first save must survive untouched.
    $this->assertSame('echoai', $pluginConfig['automator_ai_provider']);
    $this->assertSame('default', $pluginConfig['automator_ai_model']);
  }

  /**
   * Creating an automator with a rule incompatible with the field type fails.
   */
  public function testCreateAutomatorRejectsIncompatibleFieldType(): void {
    $tool = $this->toolManager->createInstance('ai_automator:save_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    // llm_boolean targets boolean fields; body is text_long.
    $tool->setInputValue('rule', 'llm_boolean');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('is not compatible with field', (string) $result->getMessage());
    $this->assertNull(AiAutomator::load('node.article.body.default'));
  }

  /**
   * Creating a base-mode automator without a prompt fails cleanly.
   */
  public function testCreateAutomatorRequiresPromptInBaseMode(): void {
    $tool = $this->toolManager->createInstance('ai_automator:save_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->setInputValue('rule', 'llm_text_long');
    $tool->setInputValue('base_field', 'title');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('prompt is required', (string) $result->getMessage());
    $this->assertNull(AiAutomator::load('node.article.body.default'));
  }

  /**
   * Creating an automator without a rule fails cleanly.
   */
  public function testCreateAutomatorRequiresRule(): void {
    $tool = $this->toolManager->createInstance('ai_automator:save_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->execute();

    $result = $tool->getResult();
    $this->assertFalse($result->isSuccess());
    $this->assertStringContainsString('rule is required', (string) $result->getMessage());
    $this->assertNull(AiAutomator::load('node.article.body.default'));
  }

  /**
   * The automator ID is always computed from entity_type/bundle/field_name.
   */
  public function testCanonicalIdIsComputed(): void {
    $tool = $this->toolManager->createInstance('ai_automator:save_automator');
    $tool->setInputValue('entity_type', 'node');
    $tool->setInputValue('bundle', 'article');
    $tool->setInputValue('field_name', 'body');
    $tool->setInputValue('rule', 'llm_text_long');
    $tool->setInputValue('base_field', 'title');
    $tool->setInputValue('prompt', '{{ context }}');
    $tool->execute();

    $this->assertTrue($tool->getResult()->isSuccess(), (string) $tool->getResult()->getMessage());
    $this->assertSame('node.article.body.default', $tool->getOutputValue('id'));
  }

}
