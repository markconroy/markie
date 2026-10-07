<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that opening the modal builds the entity on tricky widgets.
 *
 * A single-cardinality options_select submits a scalar value that core only
 * normalizes into the expected array shape inside an #element_validate
 * callback. The modal-open AJAX callback builds the entity without running
 * validation, so without care the scalar reaches
 * WidgetBase::massageFormValues(array) and throws a TypeError. Opening the
 * modal must also not surface unrelated required-field errors.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsModalBuildEntityTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'options',
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
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    $type_name = strtolower($this->randomMachineName(8)) . '_test';
    $type = $this->drupalCreateContentType([
      'name' => $type_name,
      'type' => $type_name,
    ]);
    $this->type = $type->id();

    // The field the action button is attached to.
    $text_storage = FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $text_storage->save();
    FieldConfig::create([
      'field_storage' => $text_storage,
      'bundle' => $type_name,
      'label' => 'Text',
    ])->save();

    // A required, single-cardinality list_string rendered with options_select.
    // This is the widget whose scalar value crashes buildEntity on modal-open.
    $choice_storage = FieldStorageConfig::create([
      'field_name' => 'field_choice',
      'entity_type' => 'node',
      'type' => 'list_string',
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => [
          'a' => 'Option A',
          'b' => 'Option B',
        ],
      ],
    ]);
    $choice_storage->save();
    FieldConfig::create([
      'field_storage' => $choice_storage,
      'bundle' => $type_name,
      'label' => 'Choice',
      'required' => TRUE,
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent('field_text', [
      'type' => 'string_textfield',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-be' => [
            'enabled' => '1',
            'button_label' => 'Generate text',
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => 'refinable_texts_for_textfield',
            'enable_refinement' => '1',
            'refinement_modal_title' => NULL,
          ],
        ],
      ],
    ]);
    $form_display->setComponent('field_choice', [
      'type' => 'options_select',
      'region' => 'content',
    ]);
    $form_display->save();
  }

  /**
   * Opening the modal must not crash when a single select is present/unset.
   */
  public function testModalOpensWithSingleValueSelectField() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // The required select is present and unselected ('_none' scalar).
    $assertSession->fieldExists('field_choice');

    // Open the modal — this builds the entity from the raw submitted form
    // values, including the scalar select.
    $this->click('.field--name-field-text .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // No server error: the modal actually opened with its content element.
    $assertSession->elementExists('css', '.ui-dialog');
    $assertSession->fieldExists('content');

    // Constraint: opening the modal must not surface the unrelated required
    // field as an error.
    $assertSession->pageTextNotContains('Choice field is required');
  }

}
