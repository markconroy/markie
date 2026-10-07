<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the field widget actions settings form rendered by field_ui.
 *
 * Uses the always-unavailable test plugin 'unavailable_test_action' so the
 * filtering and messaging can be exercised through the real "Manage form
 * display" form without JavaScript and without the AI Automators module.
 *
 * Deprecations are ignored for this class because every non-JS request through
 * BrowserTestBase hits core's TestHttpClientMiddleware, which calls the
 * deprecated Symfony Request::get(). That core deprecation cannot be fixed from
 * this module and would otherwise fail run-tests.sh's --fail-on-deprecation.
 * Other test suites still fail on deprecations.
 *
 * @group field_widget_actions
 */
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsSettingsFormTest extends BrowserTestBase {

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
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * The content type id.
   *
   * @var string
   */
  protected string $type;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'administer node fields',
      'administer node form display',
      'access administration pages',
    ]));
    $this->type = $this->drupalCreateContentType(['type' => 'test_page'])->id();

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'bundle' => $this->type,
      'label' => 'Text',
    ])->save();
  }

  /**
   * Unavailable actions are filtered out and the message is shown.
   */
  public function testUnavailableActionFilteredAndMessageShown(): void {
    $this->configureComponent();
    $this->openWidgetSettings();

    $assert = $this->assertSession();
    $assert->pageTextContains('Some of the actions are unavailable for the current field.');
    $assert->optionExists('action', 'fill_textfield');
    $assert->optionNotExists('action', 'unavailable_test_action');
  }

  /**
   * A configured action that became unavailable triggers the warning.
   */
  public function testConfiguredUnavailableWarning(): void {
    $this->configureComponent([
      'fwa-test-uuid' => [
        'plugin_id' => 'unavailable_test_action',
        'enabled' => '1',
        'button_label' => 'Configured action',
        'multiple' => '0',
      ],
    ]);
    $this->openWidgetSettings();

    $this->assertSession()->pageTextContains('A configured action is currently unavailable and has been hidden.');
  }

  /**
   * The "Remove Action" button removes only that action from the sub-form.
   *
   * Regression test for https://www.drupal.org/i/3578826. The button must be
   * detected as the triggering element; otherwise the Form API falls back to
   * the first button in the form (the settings cog of the first row), which
   * closes this field's sub-form, opens another one and removes nothing.
   */
  public function testRemoveAction(): void {
    $keep = 'e1b7c2a4-0000-4a1b-8c2d-000000000001';
    $remove = 'e1b7c2a4-0000-4a1b-8c2d-000000000002';
    $this->configureComponent([
      $keep => [
        'plugin_id' => 'fill_textfield',
        'enabled' => TRUE,
        'button_label' => 'Keep me',
        'multiple' => FALSE,
        'weight' => 0,
      ],
      $remove => [
        'plugin_id' => 'suggest_texts_for_textfield',
        'enabled' => TRUE,
        'button_label' => 'Remove me',
        'multiple' => FALSE,
        'weight' => 1,
      ],
    ]);
    $this->openWidgetSettings();

    $assert = $this->assertSession();
    $prefix = 'fields[field_text][settings_edit_form][third_party_settings][field_widget_actions]';
    $assert->fieldExists($prefix . '[' . $keep . '][button_label]');
    $assert->fieldExists($prefix . '[' . $remove . '][button_label]');

    $this->submitForm([], 'field_text_remove_field_widget_action_' . $remove);

    // This field's sub-form is still the one being edited, and only the
    // removed action is gone from it.
    $assert->elementsCount('css', 'tr.field-plugin-settings-editing', 1);
    $assert->buttonExists('field_text_plugin_settings_update');
    $assert->fieldExists($prefix . '[' . $keep . '][button_label]');
    $assert->fieldNotExists($prefix . '[' . $remove . '][button_label]');

    $this->submitForm([], 'field_text_plugin_settings_update');
    $this->submitForm([], 'Save');

    $stored = EntityFormDisplay::load('node.' . $this->type . '.default')
      ->getComponent('field_text')['third_party_settings']['field_widget_actions'];
    $this->assertSame([$keep], array_keys($stored));
    $this->assertSame('Keep me', $stored[$keep]['button_label']);
  }

  /**
   * Pressing "Update" must not store the "Remove Action" button value.
   *
   * Field UI copies the third-party settings sub-form values into the display
   * without cleaning button values, so the button has to keep its own value
   * out of the saved action settings.
   */
  public function testRemoveButtonValueIsNotStored(): void {
    $uuid = 'e1b7c2a4-0000-4a1b-8c2d-000000000003';
    $this->configureComponent([
      $uuid => [
        'plugin_id' => 'fill_textfield',
        'enabled' => TRUE,
        'button_label' => 'Fill',
        'multiple' => FALSE,
        'weight' => 0,
      ],
    ]);
    $this->openWidgetSettings();
    $this->assertSession()->buttonExists('field_text_remove_field_widget_action_' . $uuid);

    $this->submitForm([], 'field_text_plugin_settings_update');
    $this->submitForm([], 'Save');

    $stored = EntityFormDisplay::load('node.' . $this->type . '.default')
      ->getComponent('field_text')['third_party_settings']['field_widget_actions'];
    $this->assertSame([$uuid], array_keys($stored));
    $this->assertArrayNotHasKey('remove', $stored[$uuid]);
    $this->assertSame('fill_textfield', $stored[$uuid]['plugin_id']);
    $this->assertSame('Fill', $stored[$uuid]['button_label']);
  }

  /**
   * Sets the field_text component to a string_textfield widget.
   *
   * @param array $third_party_settings
   *   Optional field widget action settings to seed on the component.
   */
  protected function configureComponent(array $third_party_settings = []): void {
    $display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $component = ['type' => 'string_textfield'];
    if ($third_party_settings) {
      $component['third_party_settings']['field_widget_actions'] = $third_party_settings;
    }
    $display->setComponent('field_text', $component)->save();
  }

  /**
   * Opens the field_text widget settings sub-form (no JavaScript).
   */
  protected function openWidgetSettings(): void {
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    // Submitting the cog button renders the settings sub-form server-side.
    $this->submitForm([], 'field_text_settings_edit');
  }

}
