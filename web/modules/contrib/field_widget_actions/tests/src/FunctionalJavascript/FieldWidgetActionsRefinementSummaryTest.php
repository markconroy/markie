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
 * Tests refinement of the plain summary property on a formatted field.
 *
 * The summary of a text_with_summary field is plain text even though the field
 * stores a text format, so the refinement modal must present a plain textarea
 * rather than a CKEditor. This guards the property-level detection in
 * FieldWidgetRefinableFormActionBase::targetIsFormattedText().
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsRefinementSummaryTest extends WebDriverTestBase {

  /**
   * The generated summary content of the test plugin.
   *
   * @see \Drupal\field_widget_actions_test\Plugin\FieldWidgetAction\RefinableSummaryTestAction::generateContent()
   */
  const GENERATED_SUMMARY = 'A plain-text summary that can be refined.';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'text',
    'ckeditor5',
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

    // A CKEditor 5 format, so the field's main value is a rich-text editor —
    // making the contrast with the plain summary meaningful.
    FilterFormat::create([
      'format' => 'ckeditor5',
      'name' => 'CKEditor 5',
      'roles' => [RoleInterface::AUTHENTICATED_ID],
    ])->save();
    Editor::create([
      'format' => 'ckeditor5',
      'editor' => 'ckeditor5',
      'image_upload' => ['status' => FALSE],
    ])->save();

    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
      'use text format ckeditor5',
    ]);
    $this->drupalLogin($admin_user);

    $type_name = strtolower($this->randomMachineName(8)) . '_test';
    $type = $this->drupalCreateContentType([
      'name' => $type_name,
      'type' => $type_name,
    ]);
    $this->type = $type->id();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_body',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $type_name,
      'label' => 'Body',
      // Display the summary so it renders as a visible textarea, and lock the
      // value to the CKEditor 5 format.
      'settings' => [
        'display_summary' => TRUE,
        'allowed_formats' => ['ckeditor5'],
      ],
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent('field_body', [
      'type' => 'text_textarea_with_summary',
      'region' => 'content',
      'settings' => ['show_summary' => TRUE],
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-sum' => [
            'enabled' => '1',
            'button_label' => 'Refinable summary',
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => 'refinable_summary_for_textfield',
            'enable_refinement' => '1',
            'refinement_modal_title' => NULL,
          ],
        ],
      ],
    ]);
    $form_display->save();
  }

  /**
   * The summary modal uses a plain textarea, not a CKEditor, and round-trips.
   */
  public function testSummaryRefinementUsesPlainTextarea() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Open the summary refinement modal.
    $this->click('.field--name-field-body .field-widget-action-refinable_summary_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // The content element must be a plain textarea (name "content"), and there
    // must be no CKEditor inside the dialog — a misclassification would render
    // a text_format element ("content[value]") with a .ck-editor.
    $assertSession->fieldExists('content');
    $assertSession->fieldValueEquals('content', self::GENERATED_SUMMARY);
    $assertSession->elementNotExists('css', '.ui-dialog .ck-editor');
    $assertSession->fieldNotExists('content[value]');

    // Refine: plain content round-trips through the rebuilt modal.
    $page->fillField('refinement_prompt', 'make it shorter');
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $refined = self::GENERATED_SUMMARY . ' Refined with: make it shorter.';
    $assertSession->fieldValueEquals('content', $refined);
    $assertSession->elementNotExists('css', '.ui-dialog .ck-editor');
    $assertSession->pageTextContains('Refinement iteration 2');

    // Accept: the refined text lands in the field's plain summary textarea.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_body[0][summary]', $refined);
  }

  /**
   * Cancelling the summary refinement modal leaves the summary untouched.
   */
  public function testSummaryRefinementCancel() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->click('.field--name-field-body .field-widget-action-refinable_summary_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->fieldExists('content');

    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Cancel');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_body[0][summary]', '');
  }

  /**
   * Refining the summary modal with no instructions shows an error.
   */
  public function testSummaryRefinementEmptyPrompt() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->click('.field--name-field-body .field-widget-action-refinable_summary_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Please enter refinement instructions.');

    // The modal stays open as a plain textarea with the unchanged content.
    $assertSession->elementExists('css', '.ui-dialog');
    $assertSession->elementNotExists('css', '.ui-dialog .ck-editor');
    $assertSession->fieldValueEquals('content', self::GENERATED_SUMMARY);
  }

}
