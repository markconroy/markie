<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that disabled automators are excluded from the runtime config.
 *
 * A disabled AI Automator (status: FALSE) must not be returned by
 * AiAutomatorEntityModifier::entityHasConfig(), otherwise the automator
 * keeps firing on entity save even though it has been switched off.
 *
 * @group ai_automators
 *
 * @see \Drupal\ai_automators\AiAutomatorEntityModifier::entityHasConfig()
 */
class AiAutomatorEntityHasConfigTest extends KernelTestBase {

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
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * The automator config entity under test.
   */
  protected AiAutomator $automator;

  /**
   * A node to resolve the automator config against.
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'node', 'filter']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_summary',
      'entity_type' => 'node',
      'type' => 'string_long',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_summary',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Summary',
    ])->save();

    $this->automator = AiAutomator::create([
      'id' => 'node.article.field_summary.default',
      'label' => 'Summary',
      'rule' => 'llm_string',
      'input_mode' => 'base',
      'weight' => 100,
      'worker_type' => 'direct',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_summary',
      'edit_mode' => FALSE,
      'base_field' => 'title',
      'prompt' => 'Summarize {{ context }}',
      'token' => '',
      'plugin_config' => [
        'automator_rule' => 'llm_string',
        'automator_base_field' => 'title',
        'automator_worker_type' => 'direct',
      ],
    ]);
    $this->automator->save();

    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
    ]);
  }

  /**
   * Tests that an enabled automator is returned, a disabled one is not.
   */
  public function testDisabledAutomatorIsExcluded(): void {
    /** @var \Drupal\ai_automators\AiAutomatorEntityModifier $modifier */
    $modifier = $this->container->get('ai_automator.entity_modifier');

    $configs = $modifier->entityHasConfig($this->node);
    $this->assertArrayHasKey($this->automator->id(), $configs, 'An enabled automator is part of the runtime config.');
    $this->assertSame('field_summary', $configs[$this->automator->id()]['automatorConfig']['field_name']);
    $this->assertSame('title', $configs[$this->automator->id()]['automatorConfig']['base_field']);

    $this->automator->disable()->save();

    $this->assertSame([], $modifier->entityHasConfig($this->node), 'A disabled automator is excluded from the runtime config.');
  }

}
