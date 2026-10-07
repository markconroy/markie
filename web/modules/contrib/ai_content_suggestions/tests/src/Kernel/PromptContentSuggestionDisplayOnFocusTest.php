<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_content_suggestions\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that "display on focus" only marks the action that enables it.
 *
 * @group ai_content_suggestions
 */
#[Group('ai_content_suggestions')]
#[RunTestsInSeparateProcesses]
class PromptContentSuggestionDisplayOnFocusTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'field_widget_actions',
    'ai_content_suggestions',
    'node',
    'field',
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'node']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Tests the button classes for two actions on the same field.
   */
  public function testOnlyTheOptedInButtonIsMarked(): void {
    $manager = \Drupal::service('plugin.manager.field_widget_actions');
    $items = Node::create(['type' => 'page', 'title' => 'Test'])->get('title');
    // The field wrapper, and the element of the first item, which gets the
    // buttons of actions for each item.
    $field = [];
    $element = [];
    $form_state = new FormState();

    foreach (['on_focus_action' => TRUE, 'always_visible_action' => FALSE] as $action_id => $display_on_focus) {
      $action = $manager->createInstance('prompt_content_suggestion', [
        'enabled' => TRUE,
        'button_label' => $action_id,
        'settings' => ['prompt' => 'Suggest titles', 'display_on_focus' => $display_on_focus],
      ]);
      $context = ['items' => $items, 'action_id' => $action_id, 'delta' => 0];
      $action->completeFormAlter($field, $form_state, $context);
      $action->singleElementFormAlter($element, $form_state, $context);
    }

    $this->assertContains('ai-content-suggestions--on-focus', $field['#attributes']['class']);
    $this->assertContains('ai-content-suggestions--on-focus-button', $element['on_focus_action']['#attributes']['class']);
    $this->assertNotContains('ai-content-suggestions--on-focus-button', $element['always_visible_action']['#attributes']['class']);
  }

}
