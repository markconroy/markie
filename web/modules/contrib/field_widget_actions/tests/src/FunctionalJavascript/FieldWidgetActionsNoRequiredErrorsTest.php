<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Asserts that clicking a field action never surfaces required-field errors.
 *
 * Regression test for the bug where, on a form with other empty required
 * fields, clicking a direct-fill or modal action button would surface
 * required-field violations in the page response.
 *
 * Full server-side validation still runs (so values are normalized and the
 * host entity builds correctly), but clearErrorsForAction() — a #validate
 * handler attached to every action button by FieldWidgetActionBase —
 * suppresses only the required-but-empty violations while preserving genuine
 * validation errors.
 * So neither the direct-fill path (FieldWidgetActionBase) nor the modal path
 * (FieldWidgetFormActionBase) surfaces unrelated required-field violations.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsNoRequiredErrorsTest extends WebDriverTestBase {

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
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The content type machine name.
   *
   * @var string
   */
  protected string $type;

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

    // Primary field — action is configured on this one.
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
      'required' => FALSE,
    ])->save();

    // A second required field left intentionally empty during tests.
    FieldStorageConfig::create([
      'field_name' => 'field_required_other',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_required_other'),
      'bundle' => $type_name,
      'label' => 'Other required field',
      'required' => TRUE,
    ])->save();

    // A non-required email field used to produce a GENUINE validation error
    // (an invalid value), which must NOT be suppressed by the action.
    FieldStorageConfig::create([
      'field_name' => 'field_email',
      'entity_type' => 'node',
      'type' => 'email',
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_email'),
      'bundle' => $type_name,
      'label' => 'Contact email',
      'required' => FALSE,
    ])->save();

    // Add the extra fields to the form display.
    $form_display = EntityFormDisplay::load('node.' . $type_name . '.default');
    $form_display->setComponent('field_required_other', [
      'type' => 'string_textfield',
      'region' => 'content',
    ]);
    $form_display->setComponent('field_email', [
      'type' => 'email_default',
      'region' => 'content',
    ]);
    $form_display->save();
  }

  /**
   * Clicking a direct-fill action with empty required fields shows no errors.
   *
   * The 'suggest_texts_for_textfield' plugin extends FieldWidgetActionBase
   * and is a representative direct-fill action. Before the fix, clicking the
   * button surfaced the unrelated required-field violations; now
   * clearErrorsForAction() suppresses them.
   */
  public function testDirectFillActionShowsNoRequiredErrors(): void {
    $this->configureAction('suggest_texts_for_textfield', 'Suggest');
    // Visit the add-node form and leave both required fields empty.
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Click the direct-fill action button.
    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // The suggestions dialog should have opened successfully.
    $assertSession->pageTextContains('Banana');

    // No required-field error messages should appear anywhere on the page.
    $assertSession->elementNotExists('css', '.messages--error');
  }

  /**
   * A genuine (non-required) validation error is preserved on the action path.
   *
   * Suppressing only required-but-empty errors must not hide real value
   * problems: an invalid email entered in another field should still surface,
   * while the unrelated required-field violation stays suppressed.
   */
  public function testDirectFillActionPreservesGenuineValidationErrors(): void {
    $this->configureAction('suggest_texts_for_textfield', 'Suggest');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Enter an invalid email (a genuine, non-required value problem) and leave
    // the required fields empty.
    $this->getSession()->getPage()->fillField('field_email[0][value]', 'not-an-email');

    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // The suggestion dialog still opens (the action runs).
    $assertSession->pageTextContains('Banana');

    // The genuine email error is preserved and shown to the user. Assert
    // against the response markup rather than pageTextContains(): opening the
    // suggestions dialog moves browser focus and can scroll the top-of-page
    // error message out of the viewport, and WebDriver's rendered-text lookup
    // omits text that is currently scrolled out of view.
    $assertSession->elementExists('css', '.messages--error');
    $assertSession->responseContains('not-an-email');

    // The unrelated required-field violation is still suppressed.
    $assertSession->pageTextNotContains('Other required field field is required');
  }

  /**
   * Clicking a modal action with empty required fields shows no errors.
   *
   * The 'fill_textfield' plugin extends FieldWidgetFormActionBase (the modal
   * subclass). This test locks the regression for both paths in the same
   * suite.
   */
  public function testModalActionShowsNoRequiredErrors(): void {
    $this->configureAction('fill_textfield', 'Fill');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Click the modal action button.
    $this->click('.field--name-field-test .field-widget-action-fill_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    // The modal dialog should have opened.
    $assertSession->pageTextContains('New text');

    // No required-field error messages should appear outside the dialog.
    $assertSession->elementNotExists('css', '.messages--error');
  }

  /**
   * Configures the form display to attach an action plugin to field_test.
   *
   * @param string $plugin_id
   *   The action plugin ID.
   * @param string $label
   *   The button label.
   */
  protected function configureAction(string $plugin_id, string $label): void {
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent('field_test', [
      'type' => 'string_textfield',
      'region' => 'content',
      'settings' => ['size' => 60],
      'third_party_settings' => [
        'field_widget_actions' => [
          'ec6795f3-3956-4df2-bd64-980e5002129d' => [
            'enabled' => '1',
            'button_label' => $label,
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => $plugin_id,
          ],
        ],
      ],
    ]);
    $form_display->save();
  }

}
