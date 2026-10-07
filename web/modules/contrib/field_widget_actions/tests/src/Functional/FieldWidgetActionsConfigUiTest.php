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
 * Tests the refinement options in the field widget action settings UI.
 *
 * The refinement settings live on the refinement-aware base class, so they
 * must appear in the form-display settings UI for a refinement-aware action
 * and must NOT appear for an action that does not support refinement.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsConfigUiTest extends BrowserTestBase {

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
   * The content type machine name.
   *
   * @var string
   */
  protected string $type;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'administer node fields',
      'administer node form display',
    ]);
    $this->drupalLogin($admin);

    $this->type = strtolower($this->randomMachineName(8));
    $this->drupalCreateContentType(['type' => $this->type, 'name' => 'Test type']);

    foreach (['field_refine', 'field_plain'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('node', $field_name),
        'bundle' => $this->type,
        'label' => $field_name,
      ])->save();
    }

    // field_refine carries a refinement-aware action with refinement enabled
    // and a stored modal title; field_plain carries a plain (non-refinable)
    // action.
    $display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $display->setComponent('field_refine', [
      'type' => 'string_textfield',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'ec6795f3-3956-4df2-bd64-980e5002129d' => [
            'enabled' => TRUE,
            'button_label' => 'Refine',
            'multiple' => FALSE,
            'weight' => 0,
            'plugin_id' => 'refinable_texts_for_textfield',
            'enable_refinement' => TRUE,
            'refinement_modal_title' => 'Stored modal title',
            'show_result_message' => TRUE,
          ],
        ],
      ],
    ]);
    $display->setComponent('field_plain', [
      'type' => 'string_textfield',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'bc6795f3-3956-4df2-bd64-980e5002579c' => [
            'enabled' => TRUE,
            'button_label' => 'Suggest',
            'multiple' => FALSE,
            'weight' => 0,
            'plugin_id' => 'suggest_texts_for_textfield',
            'show_result_message' => TRUE,
          ],
        ],
      ],
    ]);
    $display->save();
  }

  /**
   * The refinement options show only for the refinement-aware action.
   *
   * Drupal core's TestHttpClientMiddleware triggers a symfony/http-foundation
   * Request::get() deprecation on every functional request; ignore it here so
   * --fail-on-deprecation does not fail on unrelated core test-infra noise.
   */
  #[IgnoreDeprecations]
  public function testRefinementOptionsVisibility(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $path = 'admin/structure/types/manage/' . $this->type . '/form-display';

    // Open the refinement-aware field's widget settings.
    $this->drupalGet($path);
    $page->findButton('field_refine_settings_edit')->press();

    // The refinement controls render, and the stored title round-trips.
    $assert->pageTextContains('Enable interactive refinement');
    $assert->pageTextContains('Refinement dialog title');
    $assert->fieldValueEquals(
      'fields[field_refine][settings_edit_form][third_party_settings][field_widget_actions][ec6795f3-3956-4df2-bd64-980e5002129d][refinement_modal_title]',
      'Stored modal title'
    );

    // Open the plain field's widget settings: no refinement controls.
    $this->drupalGet($path);
    $page->findButton('field_plain_settings_edit')->press();
    $assert->pageTextContains('Button label');
    $assert->pageTextNotContains('Enable interactive refinement');
    $assert->pageTextNotContains('Refinement dialog title');
  }

  /**
   * The result message options show for every action, and save what is typed.
   *
   * Unlike the refinement settings, result messaging lives on the base class,
   * so it applies to any action that fills a field. The message text fields
   * advertise the built-in default as a placeholder, since leaving them empty
   * is what selects that default.
   *
   * @see \Drupal\field_widget_actions\FieldWidgetActionBase::buildConfigurationForm()
   */
  #[IgnoreDeprecations]
  public function testResultMessageOptions(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $path = 'admin/structure/types/manage/' . $this->type . '/form-display';
    $prefix = 'fields[field_plain][settings_edit_form][third_party_settings][field_widget_actions][bc6795f3-3956-4df2-bd64-980e5002579c]';

    $this->drupalGet($path);
    $page->findButton('field_plain_settings_edit')->press();

    // Present on a plain, non-refinable action.
    $assert->pageTextContains('Show a message with the result');
    $assert->pageTextContains('Message when a value is returned');
    $assert->pageTextContains('Message when nothing is returned');

    // Messaging is on by default, so an action configured before this feature
    // existed starts reporting its outcome without being reconfigured.
    $assert->checkboxChecked($prefix . '[show_result_message]');

    // The message fields are empty, and the default text is offered as a
    // placeholder rather than prefilled — an empty value means "use default",
    // so prefilling would freeze a copy of today's wording into config.
    $assert->fieldValueEquals($prefix . '[message_success]', '');
    $assert->fieldValueEquals($prefix . '[message_empty]', '');
    $assert->elementAttributeContains(
      'css',
      'input[name="' . $prefix . '[message_empty]"]',
      'placeholder',
      'No suggestion was returned for @field.'
    );

    // Custom text typed here reaches config, so an empty stored value really
    // does mean "use the default" rather than "the form dropped the value".
    $page->fillField($prefix . '[message_empty]', 'The robot had nothing to say about @field.');
    $page->findButton('field_plain_plugin_settings_update')->press();
    $page->pressButton('Save');

    $stored = EntityFormDisplay::load('node.' . $this->type . '.default')
      ->getComponent('field_plain')['third_party_settings']['field_widget_actions']['bc6795f3-3956-4df2-bd64-980e5002579c'];
    $this->assertSame('The robot had nothing to say about @field.', $stored['message_empty']);

    // Also present on the refinement-aware action.
    $this->drupalGet($path);
    $page->findButton('field_refine_settings_edit')->press();
    $assert->pageTextContains('Show a message with the result');
  }

}
