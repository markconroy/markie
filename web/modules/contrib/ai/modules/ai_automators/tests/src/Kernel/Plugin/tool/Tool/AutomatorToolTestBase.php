<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\tool\Tool;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\tool\Tool\ToolManager;

/**
 * Shared fixture for ai_automator Tool plugin kernel tests.
 *
 * Provides an "article" node type with a text_long "body" field, which the
 * shipped "llm_text_long" and "summarize_to_text_long" automator types are
 * compatible with, and against which "llm_boolean" is incompatible (used to
 * test rule/field-type validation).
 */
abstract class AutomatorToolTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'text',
    'token',
    'filter',
    'options',
    'key',
    'ai',
    'ai_automators',
    'tool',
  ];

  /**
   * The tool plugin manager.
   *
   * @var \Drupal\tool\Tool\ToolManager
   */
  protected ToolManager $toolManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installConfig([
      'system',
      'field',
      'file',
      'node',
      'filter',
    ]);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_long',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();

    $this->toolManager = $this->container->get('plugin.manager.tool');
  }

}
