<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests removing an action through the "Manage form display" UI with Ajax.
 *
 * Regression test for https://www.drupal.org/i/3578826.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
class FieldWidgetActionsRemoveActionTest extends WebDriverTestBase {

  /**
   * UUID of the action that stays configured.
   */
  protected const KEEP_UUID = 'e1b7c2a4-0000-4a1b-8c2d-000000000001';

  /**
   * UUID of the action that gets removed.
   */
  protected const REMOVE_UUID = 'e1b7c2a4-0000-4a1b-8c2d-000000000002';

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
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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

    EntityFormDisplay::load('node.' . $this->type . '.default')
      ->setComponent('field_text', [
        'type' => 'string_textfield',
        'third_party_settings' => [
          'field_widget_actions' => [
            self::KEEP_UUID => [
              'plugin_id' => 'fill_textfield',
              'enabled' => TRUE,
              'button_label' => 'Keep me',
              'multiple' => FALSE,
              'weight' => 0,
            ],
            self::REMOVE_UUID => [
              'plugin_id' => 'suggest_texts_for_textfield',
              'enabled' => TRUE,
              'button_label' => 'Remove me',
              'multiple' => FALSE,
              'weight' => 1,
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * Removing an action keeps the sub-form open and drops only that action.
   */
  public function testRemoveAction(): void {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    $prefix = 'fields[field_text][settings_edit_form][third_party_settings][field_widget_actions]';

    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    $page->pressButton('field_text_settings_edit');
    $assert->assertWaitOnAjaxRequest();
    $assert->fieldExists($prefix . '[' . self::KEEP_UUID . '][button_label]');
    $assert->fieldExists($prefix . '[' . self::REMOVE_UUID . '][button_label]');

    // The actions are rendered in collapsed details elements; expand them so
    // the button can be clicked.
    $this->getSession()->executeScript('document.querySelectorAll("details").forEach((details) => { details.open = true; });');
    $page->pressButton('field_text_remove_field_widget_action_' . self::REMOVE_UUID);
    $assert->assertWaitOnAjaxRequest();

    // This field's sub-form is still the one being edited, and only the
    // removed action is gone from it.
    $assert->elementsCount('css', 'tr.field-plugin-settings-editing', 1);
    $assert->buttonExists('field_text_plugin_settings_update');
    $assert->fieldExists($prefix . '[' . self::KEEP_UUID . '][button_label]');
    $assert->fieldNotExists($prefix . '[' . self::REMOVE_UUID . '][button_label]');

    $page->pressButton('field_text_plugin_settings_update');
    $assert->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $assert->pageTextContains('Your settings have been saved.');

    $stored = EntityFormDisplay::load('node.' . $this->type . '.default')
      ->getComponent('field_text')['third_party_settings']['field_widget_actions'];
    $this->assertSame([self::KEEP_UUID], array_keys($stored));
    $this->assertSame('Keep me', $stored[self::KEEP_UUID]['button_label']);
    $this->assertArrayNotHasKey('remove', $stored[self::KEEP_UUID]);
  }

}
