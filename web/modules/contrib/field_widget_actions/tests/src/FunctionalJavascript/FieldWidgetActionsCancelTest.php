<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that Cancel dismisses the modal even when validation would fail.
 *
 * The modal wrapper validates the form before processing a submission, but the
 * Cancel action discards the user's input, so it must close the dialog without
 * being blocked by validation — otherwise a modal form that adds its own
 * validation would trap the user with no way out.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsCancelTest extends WebDriverTestBase {

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
   * The node type id.
   *
   * @var string
   */
  protected $type;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    $type_name = strtolower($this->randomMachineName(8)) . '_test';
    $type = $this->drupalCreateContentType([
      'name' => $type_name,
      'type' => $type_name,
    ]);
    $this->type = $type->id();

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_test'),
      'bundle' => $type_name,
      'label' => 'field_test',
    ])->save();

    $form_display = EntityFormDisplay::load('node.' . $type_name . '.default');
    $form_display->setComponent('field_test', [
      'type' => 'string_textfield',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-cancel' => [
            'enabled' => '1',
            'button_label' => 'Generate',
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => 'refinable_validating_for_textfield',
            'enable_refinement' => '1',
            'refinement_modal_title' => NULL,
          ],
        ],
      ],
    ]);
    $form_display->save();
  }

  /**
   * Insert is blocked by validation, but Cancel still closes the modal.
   */
  public function testCancelClosesDespiteValidationErrors() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->click('.field--name-field-test .field-widget-action-refinable_validating_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->fieldExists('content');

    // Insert triggers validation, which always fails: the modal stays open and
    // the error is shown.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementExists('css', '.ui-dialog');
    $assertSession->pageTextContains('Validation always fails in this test action.');

    // Cancel bypasses validation and dismisses the dialog.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Cancel');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_test[0][value]', '');
  }

}
