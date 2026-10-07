<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that refinement preserves HTML formatting for rich-text fields.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsRefinementFormatTest extends WebDriverTestBase {

  use CKEditor5TestTrait;

  /**
   * The form element name of the target field's CKEditor textarea.
   */
  const TARGET_EDITOR_NAME = 'field_body[0][value]';

  /**
   * The form element name of the modal content text_format textarea.
   */
  const MODAL_EDITOR_NAME = 'content[value]';

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

    // A text format backed by CKEditor 5.
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
      'type' => 'text_long',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $type_name,
      'label' => 'Body',
      // Lock the field to the CKEditor 5 format so both the target widget and
      // the refinement modal render a CKEditor instance deterministically.
      'settings' => ['allowed_formats' => ['ckeditor5']],
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent('field_body', [
      'type' => 'text_textarea',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'ec6795f3-3956-4df2-bd64-980e5002129d' => [
            'enabled' => '1',
            'button_label' => 'Refinable body',
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => 'refinable_texts_for_textfield',
            'enable_refinement' => '1',
            'refinement_modal_title' => NULL,
          ],
        ],
      ],
    ]);
    $form_display->save();
  }

  /**
   * Generate → Refine → Accept preserves HTML for a CKEditor field.
   */
  public function testFormattedRefinementPreservesHtml() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    // The target field renders a CKEditor instance.
    $this->waitForEditor();

    // Open the refinement modal; its content element is a CKEditor too.
    $this->click('.field--name-field-body .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->waitForElementVisible('css', '.ui-dialog .ck-editor');

    // The generated HTML list shows in the modal editor as real markup.
    $this->assertEditorContains(self::MODAL_EDITOR_NAME, '<ul>');
    $this->assertEditorContains(self::MODAL_EDITOR_NAME, '<li>One</li>');
    $this->assertEditorContains(self::MODAL_EDITOR_NAME, '<li>Two</li>');

    // Refine: the list must survive and gain the new item.
    $page->fillField('refinement_prompt', 'Kiwi');
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->waitForElementVisible('css', '.ui-dialog .ck-editor');
    $this->assertEditorContains(self::MODAL_EDITOR_NAME, '<li>One</li>');
    $this->assertEditorContains(self::MODAL_EDITOR_NAME, '<li>Kiwi</li>');
    $assertSession->pageTextContains('Refinement iteration 2');

    // Accept: the HTML list lands in the target field's editor.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $this->assertEditorContains(self::TARGET_EDITOR_NAME, '<li>One</li>');
    $this->assertEditorContains(self::TARGET_EDITOR_NAME, '<li>Kiwi</li>');
  }

  /**
   * Cancelling the formatted refinement modal leaves the editor untouched.
   */
  public function testFormattedRefinementCancel() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->waitForEditor();

    $this->click('.field--name-field-body .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->waitForElementVisible('css', '.ui-dialog .ck-editor');

    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Cancel');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');

    // The target editor never received the generated list.
    $data = (string) $this->getSession()->evaluateScript(<<<JS
(function(){
  var ta = document.querySelector('textarea[name="field_body[0][value]"]');
  var id = ta ? ta.getAttribute('data-ckeditor5-id') : null;
  var inst = id && Drupal.CKEditor5Instances ? Drupal.CKEditor5Instances.get(id) : null;
  return inst ? inst.getData() : '';
})();
JS);
    $this->assertStringNotContainsString('<li>One</li>', $data);
  }

  /**
   * Refining a formatted modal with no instructions shows an error.
   */
  public function testFormattedRefinementEmptyPrompt() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->waitForEditor();

    $this->click('.field--name-field-body .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->waitForElementVisible('css', '.ui-dialog .ck-editor');

    // Refine without entering instructions.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Please enter refinement instructions.');

    // The modal stays open with its editor and the generated list intact.
    $assertSession->elementExists('css', '.ui-dialog .ck-editor');
    $this->assertEditorContains(self::MODAL_EDITOR_NAME, '<li>One</li>');
  }

  /**
   * With refinement disabled, a formatted target takes the direct-fill path.
   *
   * The plugin's direct-fill callback fills the field without opening a modal,
   * and — because it now passes the captured target element as context — it
   * detects the rich-text target and inserts HTML through the editor.
   */
  public function testDirectFillDisabledOnFormattedTarget() {
    // Disable refinement on the configured action.
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $component = $form_display->getComponent('field_body');
    $component['third_party_settings']['field_widget_actions']['ec6795f3-3956-4df2-bd64-980e5002129d']['enable_refinement'] = '0';
    $form_display->setComponent('field_body', $component)->save();

    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $this->waitForEditor();

    // Clicking fills the field directly; no modal dialog appears.
    $this->click('.field--name-field-body .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');

    // The generated HTML list lands in the target field's editor as markup.
    $this->assertEditorContains(self::TARGET_EDITOR_NAME, '<li>One</li>');
    $this->assertEditorContains(self::TARGET_EDITOR_NAME, '<li>Two</li>');
  }

  /**
   * Asserts a CKEditor 5 instance's data contains a substring (with waiting).
   *
   * The instance is resolved from the textarea's form element name, since
   * Drupal keys CKEditor5Instances by a random data-ckeditor5-id (not the DOM
   * id, which also carries a uniqueness suffix inside the modal).
   *
   * @param string $editor_name
   *   The form element name of the editor's source textarea.
   * @param string $needle
   *   The substring expected in the editor's HTML output.
   */
  protected function assertEditorContains(string $editor_name, string $needle): void {
    $js = <<<JS
(function(){
  var ta = document.querySelector('textarea[name="$editor_name"]');
  if (!ta || !Drupal.CKEditor5Instances) { return null; }
  var id = ta.getAttribute('data-ckeditor5-id');
  var inst = id ? Drupal.CKEditor5Instances.get(id) : null;
  return inst ? inst.getData() : null;
})();
JS;
    $data = '';
    // Poll briefly: the editor may still be initializing or receiving data.
    for ($i = 0; $i < 20; $i++) {
      $data = (string) $this->getSession()->evaluateScript($js);
      if (str_contains($data, $needle)) {
        break;
      }
      usleep(250000);
    }
    $this->assertStringContainsString($needle, $data, "Editor '$editor_name' data did not contain '$needle'. Got: $data");
  }

}
