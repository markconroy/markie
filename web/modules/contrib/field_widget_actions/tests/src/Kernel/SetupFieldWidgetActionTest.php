<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests SetupFieldWidgetAction config action.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class SetupFieldWidgetActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * The config action plugin under test.
   *
   * @var \Drupal\Core\Config\Action\ConfigActionPluginInterface
   */
  protected $configAction;

  /**
   * The form display config name used across tests.
   *
   * @var string
   */
  protected string $configName = 'core.entity_form_display.node.page.default';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'page',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('title', ['type' => 'string_textfield', 'weight' => -5])->save();

    $this->configAction = $this->container
      ->get('plugin.manager.config_action')
      ->createInstance('setComponentThirdPartySetting');
  }

  /**
   * Tests that list-style settings receive auto-generated UUID keys.
   */
  public function testListStyleSettingsGenerateUuids(): void {
    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $component['third_party_settings']['field_widget_actions'] = [
      ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'Button A'],
      ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 1, 'button_label' => 'Button B'],
    ];
    $display->setComponent('title', $component);
    $display->save();

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $settings = $component['third_party_settings']['field_widget_actions'] ?? [];

    $this->assertCount(2, $settings);
    $keys = array_keys($settings);
    $this->assertTrue(Uuid::isValid($keys[0]));
    $this->assertTrue(Uuid::isValid($keys[1]));
    $this->assertNotSame($keys[0], $keys[1], 'Each list item must get a distinct UUID.');
    $this->assertSame('Button A', $settings[$keys[0]]['button_label']);
    $this->assertSame('Button B', $settings[$keys[1]]['button_label']);
  }

  /**
   * Tests that explicit UUID-keyed settings are stored unchanged.
   */
  public function testExplicitUuidKeysPreserved(): void {
    $uuid = 'bc6795f3-3956-4df2-bd64-980e5002579c';
    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $component['third_party_settings']['field_widget_actions'] = [
      $uuid => ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'Explicit'],
    ];
    $display->setComponent('title', $component);
    $display->save();

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $settings = $component['third_party_settings']['field_widget_actions'] ?? [];

    $this->assertArrayHasKey($uuid, $settings);
    $this->assertCount(1, $settings);
    $this->assertSame('Explicit', $settings[$uuid]['button_label']);
  }

  /**
   * Tests that mixed settings are saved properly.
   */
  public function testMixedStyleSettings(): void {
    $uuid = 'bc6795f3-3956-4df2-bd64-980e5002579c';
    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $component['third_party_settings']['field_widget_actions'] = [
      $uuid => ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'First'],
      ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'Second'],
    ];
    $display->setComponent('title', $component);
    $display->save();

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $settings = $component['third_party_settings']['field_widget_actions'] ?? [];

    $this->assertCount(2, $settings);
    $this->assertArrayHasKey($uuid, $settings);
    $keys = array_keys($settings);
    $this->assertTrue(Uuid::isValid($keys[0]));
    $this->assertTrue(Uuid::isValid($keys[1]));
    $this->assertNotSame($keys[0], $keys[1], 'Each list item must get a distinct UUID.');
    $labels = array_column($settings, 'button_label');
    $this->assertContains('First', $labels);
    $this->assertContains('Second', $labels);
  }

  /**
   * Tests that integer array keys in list-style settings do not cause errors.
   *
   * Regression guard for the replacePlaceholders(). The keys are converted to
   * string with `(string)`.
   */
  public function testIntegerKeysDoNotCauseTypeError(): void {
    $this->configAction->apply($this->configName, [
      'component' => 'title',
      'settings' => [
        ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'Test'],
      ],
    ]);

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $this->assertNotEmpty($component['third_party_settings']['field_widget_actions'] ?? []);
  }

  /**
   * Tests that repeated apply() calls merge rather than overwrite settings.
   */
  public function testListStyleSettingsMergeAcrossCalls(): void {
    $this->configAction->apply($this->configName, [
      'component' => 'title',
      'settings' => [
        ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'First'],
      ],
    ]);
    $this->configAction->apply($this->configName, [
      'component' => 'title',
      'settings' => [
        ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 1, 'button_label' => 'Second'],
      ],
    ]);

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $settings = $component['third_party_settings']['field_widget_actions'] ?? [];

    $this->assertCount(2, $settings);
    $labels = array_column($settings, 'button_label');
    $this->assertContains('First', $labels);
    $this->assertContains('Second', $labels);
  }

  /**
   * Tests that list-style settings receive auto-generated UUID keys in action.
   */
  public function testListStyleSettingsGenerateUuidsWithConfigAction(): void {
    $this->configAction->apply($this->configName, [
      'component' => 'title',
      'settings' => [
        ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'Button A'],
        ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 1, 'button_label' => 'Button B'],
      ],
    ]);

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $settings = $component['third_party_settings']['field_widget_actions'] ?? [];

    $this->assertCount(2, $settings);
    $keys = array_keys($settings);
    $this->assertTrue(Uuid::isValid($keys[0]));
    $this->assertTrue(Uuid::isValid($keys[1]));
    $this->assertNotSame($keys[0], $keys[1], 'Each list item must get a distinct UUID.');
    $this->assertSame('Button A', $settings[$keys[0]]['button_label']);
    $this->assertSame('Button B', $settings[$keys[1]]['button_label']);
  }

  /**
   * Tests that explicit UUID-keyed settings are stored unchanged in action.
   */
  public function testExplicitUuidKeysPreservedWithConfigAction(): void {
    $uuid = 'bc6795f3-3956-4df2-bd64-980e5002579c';
    $this->configAction->apply($this->configName, [
      'component' => 'title',
      'settings' => [
        $uuid => ['plugin_id' => 'fill_textfield', 'enabled' => TRUE, 'weight' => 0, 'button_label' => 'Explicit'],
      ],
    ]);

    $display = EntityFormDisplay::load('node.page.default');
    $component = $display->getComponent('title');
    $settings = $component['third_party_settings']['field_widget_actions'] ?? [];

    $this->assertArrayHasKey($uuid, $settings);
    $this->assertCount(1, $settings);
    $this->assertSame('Explicit', $settings[$uuid]['button_label']);
  }

}
