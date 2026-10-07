<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_widget_actions\Hook\FieldWidgetAction;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests availability filtering and messages in the widget settings form.
 *
 * Exercises Drupal\field_widget_actions\Hook\FieldWidgetAction::
 * fieldWidgetThirdPartySettingsForm() directly, without a browser and without
 * the AI Automators module, using the always-unavailable test plugin
 * 'unavailable_test_action'.
 *
 * @group field_widget_actions
 * @coversDefaultClass \Drupal\field_widget_actions\Hook\FieldWidgetAction
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsSettingsFormTest extends KernelTestBase {

  /**
   * Field widget action schema validation disabled.
   *
   * @var bool
   *
   * @todo Remove once the field_widget_actions third-party-settings config
   *   schema is complete (see FieldWidgetActionsUiEntityReferenceSelectTest).
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'node']);
    NodeType::create(['type' => 'test', 'name' => 'Test'])->save();
    $this->createField('field_text', 'string');
    $this->createField('field_bool', 'boolean');
  }

  /**
   * Mixed case: an available action remains, an unavailable one is filtered.
   */
  public function testUnavailableActionFilteredFromOptions(): void {
    $element = $this->buildSettingsForm('field_text', 'string_textfield');

    $option_ids = [];
    foreach ($element['new']['action']['#options'] as $group) {
      $option_ids += $group;
    }
    $this->assertArrayHasKey('fill_textfield', $option_ids);
    $this->assertArrayNotHasKey('unavailable_test_action', $option_ids);
    // The "Some of the actions are unavailable…" message is accessible.
    $this->assertTrue($element['new']['unavailable']['#access']);
  }

  /**
   * A broken discovered plugin is skipped instead of crashing the form build.
   */
  public function testBrokenActionInstantiationIsSkipped(): void {
    $element = $this->buildSettingsForm('field_text', 'string_textfield');

    $option_ids = [];
    foreach ($element['new']['action']['#options'] as $group) {
      $option_ids += $group;
    }
    $this->assertArrayHasKey('fill_textfield', $option_ids);
    $this->assertArrayNotHasKey('broken_test_action', $option_ids);
  }

  /**
   * All allowed actions unavailable and none configured: generic message.
   */
  public function testNoAvailableActionsMessage(): void {
    $element = $this->buildSettingsForm('field_bool', 'boolean_checkbox');

    $this->assertStringContainsString('There are no available Field Widget Actions', (string) $element['#markup']);
  }

  /**
   * Scenario C, no actions remain: a configured action became unavailable.
   */
  public function testConfiguredUnavailableMessageWhenNoneRemain(): void {
    $element = $this->buildSettingsForm('field_bool', 'boolean_checkbox', $this->fwaConfig());

    $this->assertStringContainsString('A configured action is currently unavailable', (string) $element['#markup']);
  }

  /**
   * Scenario C, other actions remain: the warning is shown in the panel.
   */
  public function testConfiguredUnavailableWarningWhenOthersRemain(): void {
    $element = $this->buildSettingsForm('field_text', 'string_textfield', $this->fwaConfig());

    $this->assertArrayHasKey('enabled_unavailable', $element);
    $this->assertStringContainsString('A configured action is currently unavailable', (string) $element['enabled_unavailable']['#markup']);
  }

  /**
   * Builds the third-party settings form element for a field's widget.
   *
   * @param string $field_name
   *   The field to configure.
   * @param string $widget_type
   *   The widget plugin id.
   * @param array $third_party_settings
   *   Field widget action third-party settings to seed on the component.
   *
   * @return array
   *   The render array returned by the hook.
   */
  protected function buildSettingsForm(string $field_name, string $widget_type, array $third_party_settings = []): array {
    $display = EntityFormDisplay::load('node.test.default') ?: EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $component = ['type' => $widget_type];
    if ($third_party_settings) {
      $component['third_party_settings']['field_widget_actions'] = $third_party_settings;
    }
    $display->setComponent($field_name, $component)->save();

    $widget = $display->getRenderer($field_name);
    $field_definition = FieldConfig::loadByName('node', 'test', $field_name);

    $hook = new FieldWidgetAction(
      $this->container->get('plugin.manager.field_widget_actions'),
      $this->container->get('uuid'),
      $this->container->get('module_handler'),
    );
    return $hook->fieldWidgetThirdPartySettingsForm($widget, $field_definition, 'default', ['#bundle' => 'test'], new FormState());
  }

  /**
   * A saved field-widget-action config referencing the unavailable plugin.
   *
   * @return array
   *   Third-party settings keyed by a generated uuid.
   */
  protected function fwaConfig(): array {
    return [
      $this->container->get('uuid')->generate() => [
        'plugin_id' => 'unavailable_test_action',
        'enabled' => '1',
        'button_label' => 'Configured action',
        'multiple' => '0',
      ],
    ];
  }

  /**
   * Creates a single-value field on the test node type.
   *
   * @param string $field_name
   *   The field name.
   * @param string $type
   *   The field type.
   */
  protected function createField(string $field_name, string $type): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => $field_name,
    ])->save();
  }

}
