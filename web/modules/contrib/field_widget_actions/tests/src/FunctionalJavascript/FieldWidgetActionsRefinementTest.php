<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the interactive refinement flow of field widget actions.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsRefinementTest extends WebDriverTestBase {

  /**
   * The generated content of the test plugin.
   *
   * @see \Drupal\field_widget_actions_test\Plugin\FieldWidgetAction\RefinableTextsTestAction::generateContent()
   */
  const GENERATED_CONTENT = 'This is generated content that can be refined iteratively.';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_test',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
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
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $type_name,
      'label' => 'field_test',
      'required' => TRUE,
    ]);
    $instance->save();
  }

  /**
   * Tests the full refinement flow: generate, refine, insert.
   */
  public function testRefinementFlow() {
    $this->configureFormDisplay('Refinable text', TRUE, 'Refine my text');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Open the modal and verify the generated content and the refinement
    // controls are present.
    $this->click('.field--name-field-test .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementTextContains('css', '.ui-dialog-title', 'Refine my text');
    $assertSession->fieldValueEquals('content', static::GENERATED_CONTENT);
    $assertSession->fieldExists('refinement_prompt');

    // Refine the content and verify the modal is refreshed with the refined
    // content.
    $page->fillField('refinement_prompt', 'make it shorter');
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $refined = static::GENERATED_CONTENT . ' Refined with: make it shorter.';
    $assertSession->fieldValueEquals('content', $refined);
    $assertSession->pageTextContains('Refinement iteration 2');
    $assertSession->fieldValueEquals('refinement_prompt', '');

    // Refine a second time to verify the rebuilt modal stays functional.
    $page->fillField('refinement_prompt', 'more formal');
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $refined .= ' Refined with: more formal.';
    $assertSession->fieldValueEquals('content', $refined);
    $assertSession->pageTextContains('Refinement iteration 3');

    // Insert the refined content into the field.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_test[0][value]', $refined);
  }

  /**
   * Tests that refining without instructions shows an error in the modal.
   */
  public function testRefinementEmptyPrompt() {
    $this->configureFormDisplay('Refinable text', TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->click('.field--name-field-test .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // Refine without entering instructions.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Please enter refinement instructions.');

    // The modal stays open with the unchanged content.
    $assertSession->elementExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('content', static::GENERATED_CONTENT);
  }

  /**
   * Tests that cancel closes the modal without touching the field.
   */
  public function testRefinementCancel() {
    $this->configureFormDisplay('Refinable text', TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->click('.field--name-field-test .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Cancel');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_test[0][value]', '');
  }

  /**
   * Tests cancelling after several refine iterations leaves a clean state.
   *
   * Also implicitly exercises temp-store cleanup: after cancelling, re-opening
   * the action must start a fresh modal at iteration 1.
   */
  public function testRefineMultipleThenCancel() {
    $this->configureFormDisplay('Refinable text', TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->click('.field--name-field-test .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // Refine twice to reach iteration 3.
    $page->fillField('refinement_prompt', 'shorter');
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $page->fillField('refinement_prompt', 'formal');
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Refine');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Refinement iteration 3');

    // Cancel: the dialog closes and the field stays empty.
    $page->find('css', '.ui-dialog-buttonpane')->pressButton('Cancel');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_test[0][value]', '');

    // Re-opening starts a fresh modal at iteration 1 (no stale iteration text).
    $this->click('.field--name-field-test .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->fieldValueEquals('content', static::GENERATED_CONTENT);
    $assertSession->pageTextNotContains('Refinement iteration 2');
  }

  /**
   * Tests the direct field-fill flow when refinement is disabled.
   */
  public function testDirectFillWhenRefinementDisabled() {
    $this->configureFormDisplay('Refinable text', FALSE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Clicking the button fills the field directly, no modal is shown.
    $this->click('.field--name-field-test .field-widget-action-refinable_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->elementNotExists('css', '.ui-dialog');
    $assertSession->fieldValueEquals('field_test[0][value]', static::GENERATED_CONTENT);
  }

  /**
   * Helper to set up the form display with the refinable test action.
   *
   * @param string $label
   *   The label of the action.
   * @param bool $enable_refinement
   *   Whether interactive refinement should be enabled.
   * @param string|null $modal_title
   *   The refinement dialog title or NULL.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function configureFormDisplay($label, $enable_refinement = FALSE, $modal_title = NULL) {
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
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => 'refinable_texts_for_textfield',
            'enable_refinement' => $enable_refinement ? '1' : '0',
            'refinement_modal_title' => $modal_title,
          ],
        ],
      ],
    ];
    $form_display->setComponent('field_test', $display_options);
    $form_display->save();
  }

}
