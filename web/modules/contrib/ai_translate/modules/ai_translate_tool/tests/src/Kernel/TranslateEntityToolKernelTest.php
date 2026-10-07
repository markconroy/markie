<?php

namespace Drupal\Tests\ai_translate_tool\Kernel;

use Drupal\Tests\ai_translate\Kernel\EntityTranslationOrchestratorKernelTest;
use Drupal\tool\Tool\ToolInterface;
use Drupal\tool\Tool\ToolManager;

/**
 * Kernel tests for the optional Tool integration.
 *
 * @group ai_translate
 */
class TranslateEntityToolKernelTest extends EntityTranslationOrchestratorKernelTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'language',
    'content_translation',
    'ai',
    'ai_translate',
    'tool',
    'ai_translate_tool',
  ];

  /**
   * The tool plugin manager.
   *
   * @var \Drupal\tool\Tool\ToolManager
   */
  protected ToolManager $toolPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->toolPluginManager = $this->container->get('plugin.manager.tool');
  }

  /**
   * Tests that the tool plugin can be instantiated and executed.
   */
  public function testTranslateEntityToolExecutes(): void {
    $translator = $this->createUser([
      'create ai content translation',
      'bypass node access',
      'administer nodes',
      'translate any entity',
      'create content translations',
    ], 'translator-user');
    $this->setCurrentUser($translator);

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Hello world',
      'body' => [
        'value' => 'Body text',
        'format' => 'plain_text',
      ],
      'langcode' => 'en',
      'status' => TRUE,
    ]);

    $tool = $this->toolPluginManager->createInstance('ai_translate_tool:translate_entity');
    $this->assertInstanceOf(ToolInterface::class, $tool);

    $tool->setInputValue('entity_type_id', 'node');
    $tool->setInputValue('entity_id', (string) $node->id());
    $tool->setInputValue('target_language', 'fr');
    $tool->setInputValue('source_language', 'en');

    $this->assertTrue($tool->access($translator));

    $tool->execute();
    $this->assertTrue($tool->getResultStatus());
    $this->assertSame('Content translated successfully.', (string) $tool->getResultMessage());

    $result = $tool->getResult();
    $contextValues = $result->getContextValues();
    $this->assertSame('success', $contextValues['status']);
    $this->assertSame('Content translated successfully.', $contextValues['message']);
    $this->assertSame('node', $contextValues['entity_type_id']);
    $this->assertSame((string) $node->id(), $contextValues['entity_id']);
    $this->assertSame('[fr] Hello world', $contextValues['translated_entity_label']);
    $this->assertSame('success', $tool->getOutputValue('status'));
    $this->assertSame('Content translated successfully.', $tool->getOutputValue('message'));
    $this->assertSame('node', $tool->getOutputValue('entity_type_id'));
    $this->assertSame([], $tool->getOutputValue('failures'));
  }

  /**
   * Tests access denial for the tool plugin.
   */
  public function testTranslateEntityToolAccessDenied(): void {
    $translator = $this->createUser([
      'create ai content translation',
      'bypass node access',
      'administer nodes',
      'translate any entity',
      'create content translations',
    ], 'translator-user');
    $this->setCurrentUser($translator);

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Hello world',
      'body' => [
        'value' => 'Body text',
        'format' => 'plain_text',
      ],
      'langcode' => 'en',
      'status' => TRUE,
    ]);

    $deniedUser = $this->createUser([], 'denied-user');

    $tool = $this->toolPluginManager->createInstance('ai_translate_tool:translate_entity');
    $tool->setInputValue('entity_type_id', 'node');
    $tool->setInputValue('entity_id', (string) $node->id());
    $tool->setInputValue('target_language', 'fr');

    $this->assertFalse($tool->access($deniedUser));
  }

  /**
   * Tests invalid language input validation.
   */
  public function testTranslateEntityToolRejectsInvalidLanguage(): void {
    $translator = $this->createUser([
      'create ai content translation',
      'bypass node access',
      'administer nodes',
      'translate any entity',
      'create content translations',
    ], 'translator-user');
    $this->setCurrentUser($translator);

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Hello world',
      'body' => [
        'value' => 'Body text',
        'format' => 'plain_text',
      ],
      'langcode' => 'en',
      'status' => TRUE,
    ]);

    $tool = $this->toolPluginManager->createInstance('ai_translate_tool:translate_entity');
    $tool->setInputValue('entity_type_id', 'node');
    $tool->setInputValue('entity_id', (string) $node->id());
    $tool->setInputValue('target_language', 'zz');

    $tool->execute();
    $this->assertFalse($tool->getResultStatus());
    $this->assertStringContainsString('invalid input', (string) $tool->getResultMessage());
  }

}
