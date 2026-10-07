<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Basic functional JS tests for the field widget actions module.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsUiTextTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_test',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
    'ckeditor5',
    'text',
  ];

  /**
   * The node type id.
   *
   * @var string
   */
  protected $type;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    // Create content type.
    $type_name = strtolower($this->randomMachineName(8)) . '_test';
    $type = $this->drupalCreateContentType([
      'name' => $type_name,
      'type' => $type_name,
    ]);
    $this->type = $type->id();

    // Create required test field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 3,
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $type_name,
      'label' => 'field_test',
      'required' => TRUE,
    ]);
    $instance->save();

    // Create a CKEditor5 text format available to all authenticated users.
    FilterFormat::create([
      'format' => 'cke5_test',
      'name' => 'CKEditor 5 Test',
      'roles' => [RoleInterface::AUTHENTICATED_ID],
      'filters' => [],
    ])->save();

    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'cke5_test',
      'image_upload' => ['status' => FALSE],
      'settings' => [
        'toolbar' => ['items' => ['bold', 'italic']],
        'plugins' => [],
      ],
    ])->save();
  }

  /**
   * Tests the suggestion-based Field Widget Action multiple button.
   */
  public function testSingleFieldWidgetActionSuggestionMultiple() {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Suggest texts multiple', TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Open the suggestion modal and verify the suggestions are present.
    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $assertSession->pageTextContains('Banana');
    $assertSession->pageTextContains('Apple');
    $assertSession->pageTextContains('Kiwi');

    // Click a suggestion and verify the text field value is updated.
    $this->getSession()->getPage()->pressButton('Apple');
    $this->getSession()->wait(500);
    $assertSession->fieldValueEquals('field_test[0][value]', 'Apple');
  }

  /**
   * Tests the suggestion-based Field Widget Action single button.
   */
  public function testSingleFieldWidgetActionSuggestion() {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Suggest texts');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Open the suggestion modal and verify the suggestions are present.
    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $assertSession->pageTextContains('Banana');
    $assertSession->pageTextContains('Apple');
    $assertSession->pageTextContains('Kiwi');

    // Click a suggestion and verify the text field value is updated.
    $this->getSession()->getPage()->pressButton('Apple');
    $this->getSession()->wait(500);
    $assertSession->fieldValueEquals('field_test[0][value]', 'Apple');
  }

  /**
   * Tests the form-based Field Widget Action multiple button.
   */
  public function testFormBasedFieldWidgetActionMultiple() {
    $this->configureFormDisplay('fill_textfield', 'Fill plain text field lorem multiple', TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $assertSession->fieldExists('field_test[0][value]');

    // Click the button.
    $this->click('.field--name-field-test .field-widget-action-fill_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('New text');
    $assertSession->pageTextContains('How many times to repeat the text');

    // Fill in the form.
    $getSession = $this->getSession();
    $getSession->getPage()->fillField('new_text', 'Banana ');
    $getSession->getPage()->fillField('count', '4');
    $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');

    // Check that the new text is inserted.
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->fieldValueEquals('field_test[0][value]', 'Banana Banana Banana Banana ');
  }

  /**
   * Tests the form-based Field Widget Action single button.
   */
  public function testFormBasedFieldWidgetAction() {
    $this->configureFormDisplay('fill_textfield', 'Fill plain text field lorem');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $assertSession->fieldExists('field_test[0][value]');

    // Click the button.
    $this->click('.field--name-field-test .field-widget-action-fill_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('New text');
    $assertSession->pageTextContains('How many times to repeat the text');

    // Fill in the form.
    $getSession = $this->getSession();
    $getSession->getPage()->fillField('new_text', 'Banana ');
    $getSession->getPage()->fillField('count', '4');
    $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');

    // Check that the new text is inserted.
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->fieldValueEquals('field_test[0][value]', 'Banana Banana Banana Banana ');
  }

  /**
   * Tests that input and change events are fired on a plain text field.
   */
  public function testSuggestionFiresEventsOnTextField(): void {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Suggest texts');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Register event listeners before opening the dialog.
    $this->getSession()->executeScript(<<<JS
      window.fwaFiredEvents = [];
      var field = document.querySelector('[data-drupal-selector="edit-field-test-0-value"]');
      ['input', 'change'].forEach(function(type) {
        field.addEventListener(type, function() { window.fwaFiredEvents.push(type); });
      });
    JS);

    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Apple');
    $this->getSession()->wait(1000);

    $assertSession->fieldValueEquals('field_test[0][value]', 'Apple');

    $firedEvents = $this->getSession()->evaluateScript('window.fwaFiredEvents');
    $this->assertContains('input', $firedEvents, 'input event was fired when a suggestion was accepted');
    $this->assertContains('change', $firedEvents, 'change event was fired when a suggestion was accepted');
  }

  /**
   * Tests that accepting a suggestion updates the CKEditor5 textarea content.
   */
  public function testSuggestionSetsCkeditorContent(): void {
    $this->configureCkeditorFormDisplay('suggest_texts_for_textfield', 'Suggest texts');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Select the CKEditor5 format to activate the editor.
    $assertSession->waitForElement('css', '.field--name-body .ck-editor__editable');

    $this->click('.field--name-body .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Apple');

    // Wait for CKEditor to reflect the suggestion content.
    $this->assertJsCondition(
      "document.querySelector('.field--name-body .ck-editor__editable').ckeditorInstance.getData().includes('Apple')"
    );
  }

  /**
   * Helper to set up the form display with a specific action.
   *
   * @param string $plugin_id
   *   The plugin ID of the action.
   * @param string $label
   *   The label of the action.
   * @param bool $multiple
   *   Whether the action should be multiple.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function configureFormDisplay($plugin_id, $label, $multiple = FALSE) {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');

    $display_options = [
      'type' => 'string_textfield',
      'region' => 'content',
      'settings' => [
        'size' => 60,
      ],
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-1234' => [
            'enabled' => '1',
            'button_label' => $label,
            'multiple' => $multiple ? '1' : '0',
            'weight' => '0',
            'plugin_id' => $plugin_id,
          ],
        ],
      ],
    ];
    $form_display->setComponent('field_test', $display_options);
    $form_display->save();
  }

  /**
   * Helper to configure the form display for the text_long CKEditor field.
   *
   * @param string $plugin_id
   *   The plugin ID of the action.
   * @param string $label
   *   The label of the action.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function configureCkeditorFormDisplay(string $plugin_id, string $label): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $display_options = [
      'type' => 'text_textarea',
      'region' => 'content',
      'settings' => [
        'rows' => 5,
      ],
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-5678' => [
            'enabled' => '1',
            'button_label' => $label,
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => $plugin_id,
          ],
        ],
      ],
    ];
    $form_display->setComponent('body', $display_options);
    $form_display->save();
  }

}
