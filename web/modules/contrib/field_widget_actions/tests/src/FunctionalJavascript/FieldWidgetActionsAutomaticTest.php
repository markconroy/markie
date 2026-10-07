<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the automatic field widget action trigger.
 *
 * Verifies that actions configured with 'automatic' => TRUE fire on form load
 * without requiring a manual button click, and that actions without it do not.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsAutomaticTest extends WebDriverTestBase {

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
  }

  /**
   * Tests that an automatic action fires on form load.
   */
  public function testAutomaticActionFiresOnLoad() {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Suggest texts', FALSE, TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // The suggestions modal should open automatically without clicking.
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Banana');
    $assertSession->pageTextContains('Apple');
    $assertSession->pageTextContains('Kiwi');
  }

  /**
   * Tests that a non-automatic action does not fire on form load.
   */
  public function testNonAutomaticActionDoesNotFire() {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Suggest texts', FALSE, FALSE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // The suggestions modal should NOT open automatically.
    $this->getSession()->wait(500);
    $assertSession->pageTextNotContains('Banana');
    $assertSession->pageTextNotContains('Apple');
    $assertSession->pageTextNotContains('Kiwi');

    // But the button should be present and work when clicked.
    $assertSession->elementExists('css', '.field-widget-action-suggest_texts_for_textfield');
    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Banana');
  }

  /**
   * Tests that the data-fwa-automatic attribute is rendered correctly.
   */
  public function testAutomaticDataAttribute() {
    // Configure with automatic enabled.
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Auto button', FALSE, TRUE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // The button should have the data-fwa-automatic attribute.
    $button = $assertSession->elementExists('css', '.field-widget-action-suggest_texts_for_textfield');
    $this->assertEquals('true', $button->getAttribute('data-fwa-automatic'));
  }

  /**
   * Tests that non-automatic button lacks the data attribute.
   */
  public function testNonAutomaticLacksDataAttribute() {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Manual button', FALSE, FALSE);
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    $button = $assertSession->elementExists('css', '.field-widget-action-suggest_texts_for_textfield');
    $this->assertNull($button->getAttribute('data-fwa-automatic'));
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
   * @param bool $automatic
   *   Whether the action should trigger automatically.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function configureFormDisplay(string $plugin_id, string $label, bool $multiple = FALSE, bool $automatic = FALSE): void {
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
            'automatic' => $automatic ? '1' : '0',
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

}
