<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that AiPromptHelper::renderTokenPrompt() handles unresolved tokens.
 *
 * Regression test for issue #3586535: when an optional field is empty, the
 * raw token string was previously passed to the AI provider instead of being
 * stripped. This test verifies the fix holds and that populated tokens still
 * resolve correctly.
 *
 * @group ai_automators
 */
class AiPromptHelperTokenTest extends KernelTestBase {

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
  ];

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
      'field_name' => 'field_user_prompt',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_user_prompt',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'User Prompt',
    ])->save();
  }

  /**
   * Tests that an unresolved token is stripped when the field is empty.
   *
   * Without the fix, the raw string '[node:field_user_prompt:value]' would
   * appear in the prompt sent to the AI provider.
   */
  public function testUnresolvedTokenIsStripped(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
    ]);
    $node->save();

    /** @var \Drupal\ai_automators\AiPromptHelper $prompt_helper */
    $prompt_helper = \Drupal::service('ai_automator.prompt_helper');
    $prompt = 'Write a summary. Extra instructions: [node:field_user_prompt:value]';

    $result = $prompt_helper->renderTokenPrompt($prompt, $node);

    $this->assertStringNotContainsString(
      '[node:field_user_prompt:value]',
      $result,
      'Raw token must not appear in the prompt when the field is empty.'
    );
  }

  /**
   * Tests that a populated field token is replaced with its value.
   *
   * Ensures the fix does not break normal token resolution.
   */
  public function testResolvedTokenIsReplaced(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'field_user_prompt' => 'Be concise.',
    ]);
    $node->save();

    /** @var \Drupal\ai_automators\AiPromptHelper $prompt_helper */
    $prompt_helper = \Drupal::service('ai_automator.prompt_helper');
    $prompt = 'Write a summary. Extra instructions: [node:field_user_prompt:value]';

    $result = $prompt_helper->renderTokenPrompt($prompt, $node);

    $this->assertStringContainsString(
      'Be concise.',
      $result,
      'Populated field value must appear in the rendered prompt.'
    );
    $this->assertStringNotContainsString(
      '[node:field_user_prompt:value]',
      $result,
      'Raw token must not appear after resolution.'
    );
  }

}
