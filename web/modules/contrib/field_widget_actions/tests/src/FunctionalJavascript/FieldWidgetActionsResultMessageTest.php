<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\field_widget_actions_test\Plugin\FieldWidgetAction\RebuildFillTestAction;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that an action reports its outcome to the author.
 *
 * An action that returns nothing replaces the widget with identical markup,
 * so the click is indistinguishable from no click at all. These tests pin the
 * feedback that makes the outcome visible, for both ways a plugin can report
 * a result: a fill command on an AjaxResponse, and a render array whose value
 * reached the entity during the submit phase.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
class FieldWidgetActionsResultMessageTest extends WebDriverTestBase {

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

    $type = $this->drupalCreateContentType(['type' => 'test_page']);
    $this->type = $type->id();

    foreach (['field_empty', 'field_rebuild', 'field_suggest'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('node', $field_name),
        'bundle' => $this->type,
        'label' => ucfirst(substr($field_name, 6)),
      ])->save();
    }
  }

  /**
   * Attaches an action to a field on the default form display.
   *
   * @param string $field_name
   *   The field to attach the action to.
   * @param string $plugin_id
   *   The action plugin id.
   * @param array $settings
   *   Additional action configuration.
   */
  protected function attachAction(string $field_name, string $plugin_id, array $settings = []): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent($field_name, [
      'type' => 'string_textfield',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'uuid-' . $field_name => [
            'enabled' => TRUE,
            'button_label' => 'Run action',
            'multiple' => FALSE,
            'weight' => 0,
            'plugin_id' => $plugin_id,
          ] + $settings,
        ],
      ],
    ]);
    $form_display->save();
  }

  /**
   * A command-based action that returns nothing warns the author.
   *
   * This is the reported bug: without the warning the widget is redrawn
   * unchanged and the button looks broken.
   */
  public function testEmptyFillWarns(): void {
    $this->attachAction('field_empty', 'empty_fill', [
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();

    $this->click('.field--name-field-empty .field-widget-action-empty_fill');
    $assert->assertWaitOnAjaxRequest();

    $assert->pageTextContains('No suggestion was returned for Empty.');
  }

  /**
   * A configured message replaces the built-in default text.
   */
  public function testCustomEmptyMessage(): void {
    $this->attachAction('field_empty', 'empty_fill', [
      'message_empty' => 'The robot had nothing to say about @field.',
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();

    $this->click('.field--name-field-empty .field-widget-action-empty_fill');
    $assert->assertWaitOnAjaxRequest();

    $assert->pageTextContains('The robot had nothing to say about Empty.');
    $assert->pageTextNotContains('No suggestion was returned for Empty.');
  }

  /**
   * Disabling the setting suppresses the message entirely.
   */
  public function testMessagingCanBeDisabled(): void {
    $this->attachAction('field_empty', 'empty_fill', [
      'show_result_message' => FALSE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();

    $this->click('.field--name-field-empty .field-widget-action-empty_fill');
    $assert->assertWaitOnAjaxRequest();

    $assert->pageTextNotContains('No suggestion was returned');
    $assert->pageTextNotContains('A suggestion was added');
  }

  /**
   * A rebuild-based action reports both outcomes.
   *
   * This is the AI Automators shape: the value never appears in the AJAX
   * return value, so the outcome can only be told by reading the field back
   * off the rebuilt entity.
   */
  public function testRebuildActionReportsBothOutcomes(): void {
    // Produces a value.
    $this->attachAction('field_rebuild', 'rebuild_fill', [
      'produce_value' => TRUE,
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();

    $this->click('.field--name-field-rebuild .field-widget-action-rebuild_fill');
    $assert->assertWaitOnAjaxRequest();
    // The value really did land in the widget; if this fails the fixture never
    // populated anything and the message assertion below would be meaningless.
    $assert->fieldValueEquals('field_rebuild[0][value]', RebuildFillTestAction::GENERATED_VALUE);
    $assert->pageTextContains('A suggestion was added to Rebuild.');

    // Produces nothing.
    $this->attachAction('field_rebuild', 'rebuild_fill', [
      'produce_value' => FALSE,
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);

    $this->click('.field--name-field-rebuild .field-widget-action-rebuild_fill');
    $assert->assertWaitOnAjaxRequest();
    $assert->pageTextContains('No suggestion was returned for Rebuild.');
  }

  /**
   * An action that adds nothing to an already-populated field says so.
   *
   * Asking only whether the field holds a value is not enough: a field the
   * author filled in earlier is still populated after an action that returned
   * nothing, which would report success for a click that did nothing. The
   * plugin reports the outcome it actually produced instead.
   */
  public function testPopulatedFieldWithNoNewValueWarns(): void {
    $this->attachAction('field_rebuild', 'rebuild_fill', [
      'produce_value' => FALSE,
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Stand in for a value the author already had in the field, as a saved
    // node would have.
    $page->fillField('field_rebuild[0][value]', 'Existing value');

    $this->click('.field--name-field-rebuild .field-widget-action-rebuild_fill');
    $assert->assertWaitOnAjaxRequest();

    $assert->pageTextContains('No suggestion was returned for Rebuild.');
    $assert->pageTextNotContains('A suggestion was added to Rebuild.');
    // The existing value is left alone — the action added nothing, it did not
    // take anything away.
    $assert->fieldValueEquals('field_rebuild[0][value]', 'Existing value');
  }

  /**
   * Re-running an action that yields the same value warns rather than claims.
   *
   * The field is non-empty either way, so emptiness cannot answer the
   * question. An action that regenerates exactly what the author already had
   * gave them nothing new, and saying otherwise is the failure this reporting
   * exists to avoid.
   */
  public function testUnchangedValueWarnsOnSecondRun(): void {
    $this->attachAction('field_rebuild', 'rebuild_fill', [
      'produce_value' => TRUE,
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Stand in for a value already saved on the node — the same value this
    // action generates, so the run adds nothing.
    $page->fillField('field_rebuild[0][value]', RebuildFillTestAction::GENERATED_VALUE);

    $this->click('.field--name-field-rebuild .field-widget-action-rebuild_fill');
    $assert->assertWaitOnAjaxRequest();

    $assert->pageTextContains('No suggestion was returned for Rebuild.');
    $assert->pageTextNotContains('A suggestion was added to Rebuild.');
  }

  /**
   * Replacing a value counts as a suggestion, even though the count is equal.
   *
   * Guards against judging the outcome by item count instead of content: the
   * author asked for a value and got a different one, so this is a success.
   */
  public function testReplacedValueReportsSuccess(): void {
    $this->attachAction('field_rebuild', 'rebuild_fill', [
      'produce_value' => TRUE,
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $page->fillField('field_rebuild[0][value]', 'Something else entirely');

    $this->click('.field--name-field-rebuild .field-widget-action-rebuild_fill');
    $assert->assertWaitOnAjaxRequest();

    $assert->fieldValueEquals('field_rebuild[0][value]', RebuildFillTestAction::GENERATED_VALUE);
    $assert->pageTextContains('A suggestion was added to Rebuild.');
  }

  /**
   * A plugin that reports nothing gets no message put in its mouth.
   *
   * Only the plugin knows whether it produced anything, so one that never
   * calls reportProducedValue() or reportNoValueProduced() leaves the outcome
   * undetermined. Staying silent is what keeps this safe for third-party
   * plugins that predate the reporting API: they carry on as before rather
   * than gaining a message that may be untrue.
   */
  public function testUnreportedResultStaysSilent(): void {
    $this->attachAction('field_rebuild', 'rebuild_fill', [
      'produce_value' => TRUE,
      'silent' => TRUE,
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();

    $this->click('.field--name-field-rebuild .field-widget-action-rebuild_fill');
    $assert->assertWaitOnAjaxRequest();

    // The action really did run; it just did not report on itself.
    $assert->fieldValueEquals('field_rebuild[0][value]', RebuildFillTestAction::GENERATED_VALUE);
    $assert->pageTextNotContains('A suggestion was added to Rebuild.');
    $assert->pageTextNotContains('No suggestion was returned for Rebuild.');
  }

  /**
   * A suggestions action keeps its own dialog, with no extra message.
   *
   * The returnSuggestions() helper already states its own empty case in a
   * dialog and carries no fill command, so the generic message must stay out
   * of the way rather than duplicating the feedback behind the dialog.
   */
  public function testSuggestionsActionIsNotSecondGuessed(): void {
    $this->attachAction('field_suggest', 'suggest_texts_for_textfield', [
      'show_result_message' => TRUE,
    ]);
    $this->drupalGet('node/add/' . $this->type);
    $assert = $this->assertSession();

    $this->click('.field--name-field-suggest .field-widget-action-suggest_texts_for_textfield');

    // The suggestions dialog opened with its own content. Wait on the dialog
    // rather than on the AJAX queue: the response opens a modal, and the
    // dialog's own behaviors keep the queue busy past the point the content
    // is present.
    $this->assertNotNull($assert->waitForElement('css', '.ui-dialog-fwa-suggestions'));
    $assert->pageTextContains('Banana');
    // No generic message was layered on top of it.
    $assert->pageTextNotContains('No suggestion was returned for Suggest.');
    $assert->pageTextNotContains('A suggestion was added to Suggest.');
  }

}
