<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\field_widget_actions\Hook\FieldWidgetAction;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hook_field_widget_actions_childless_widgets_alter().
 *
 * Verifies that getChildlessWidgets() returns the built-in defaults and that
 * external modules can extend the list via the alter hook.
 *
 * @group field_widget_actions
 * @covers \Drupal\field_widget_actions\Hook\FieldWidgetAction::getChildlessWidgets
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsChildlessWidgetsAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * Tests that the default childless widget list contains the built-in widgets.
   */
  public function testDefaultChildlessWidgets(): void {
    $hook = $this->container->get(FieldWidgetAction::class);
    $widgets = $hook->getChildlessWidgets();

    $this->assertIsArray($widgets);
    $this->assertContains('tagify_select_widget', $widgets, 'tagify_select_widget is in the default list.');
    $this->assertContains('select2', $widgets, 'select2 is in the default list.');
    $this->assertContains('options_select', $widgets, 'options_select is in the default list.');
  }

  /**
   * Tests that hook_field_widget_actions_childless_widgets_alter() is invoked.
   *
   * The field_widget_actions_test module implements the hook and registers
   * 'custom_childless_test_widget'. This test confirms that contrib modules
   * can successfully extend the childless widgets list via the alter hook.
   */
  public function testAlterHookAddsWidgets(): void {
    $hook = $this->container->get(FieldWidgetAction::class);
    $widgets = $hook->getChildlessWidgets();

    $this->assertContains(
      'custom_childless_test_widget',
      $widgets,
      'hook_field_widget_actions_childless_widgets_alter() successfully added a widget to the list.',
    );
  }

}
