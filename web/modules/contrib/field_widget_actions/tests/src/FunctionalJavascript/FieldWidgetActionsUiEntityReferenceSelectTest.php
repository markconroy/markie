<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Basic functional JS tests for entity reference field widget action targets.
 *
 * @group field_widget_actions
 *
 * @todo Remove IgnoreDeprecations once the ai module's Constraint plugins
 * (enabled here via ai_automators) use Attribute\Constraint instead of
 * @Constraint annotations. See https://www.drupal.org/node/3395575.
 */
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
#[Group('field_widget_actions')]
class FieldWidgetActionsUiEntityReferenceSelectTest extends WebDriverTestBase {

  /**
   * General field widget action schema validation disabled.
   *
   * @var bool
   *
   * @todo This is unrelated to entity reference select tests, and is a general
   * Field Widget Action schema validation issue. Resolve this in a follow-up.
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'ai_automators',
    'node',
    'taxonomy',
    'tagify',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * The node type id.
   *
   * @var string|int|null
   */
  protected string $type;

  /**
   * The taxonomy vocabulary.
   *
   * @var \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|Vocabulary
   */
  protected $vocabulary;

  /**
   * The taxonomy terms.
   *
   * @var array
   */
  protected $terms = [];

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
      'administer taxonomy',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    // Create Content Type.
    $type = $this->drupalCreateContentType(['type' => 'test_page']);
    $this->type = $type->id();

    // Create Taxonomy Vocabulary.
    $this->vocabulary = Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);
    $this->vocabulary->save();

    // Create some Terms.
    for ($i = 1; $i <= 5; $i++) {
      $term = Term::create([
        'name' => "Term $i",
        'vid' => $this->vocabulary->id(),
      ]);
      $term->save();
      $this->terms[$i] = $term->id();
    }

    // Create Entity Reference Field (Taxonomy).
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->type,
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            $this->vocabulary->id() => $this->vocabulary->id(),
          ],
        ],
      ],
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');

    // Configure with standard entity_reference_autocomplete_tags first.
    $form_display->setComponent('field_tags', [
      'type' => 'entity_reference_autocomplete_tags',
      'third_party_settings' => [
        'field_widget_actions' => [
          'select-uuid-1234' => [
            'enabled' => '1',
            'button_label' => 'Fill',
            'multiple' => '1',
            'plugin_id' => 'fill_entity_reference_test_action',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Tests the list of plugins to select in field widget settings.
   */
  public function testUnavailablePlugins() {
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    $assertSession = $this->assertSession();
    $this->click('[data-drupal-selector="edit-fields-field-tags-settings-edit"]');
    $assertSession->waitForElementVisible('css', '[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"]');
    $this->click('[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"] summary');
    $select = $assertSession->waitForElementVisible('css', '[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"] [data-drupal-selector="edit-action"]');

    // The AI Automator plugin should not be available before an automator is
    // configured.
    $this->assertNull($select->find('css', 'option[value="automator_autocomplete_tags_on_taxonomy"]'));

    // A message should explain that some actions need pre-configuration.
    $assertSession->pageTextContains('Some of the actions are unavailable for the current field.');

    $this->createContentTagsAutomator();

    // Reload the form display page and reopen the field settings to verify
    // the plugin is now available after the automator has been configured.
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    $this->click('[data-drupal-selector="edit-fields-field-tags-settings-edit"]');
    $assertSession->waitForElementVisible('css', '[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"]');
    $this->click('[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"] summary');
    $select = $assertSession->waitForElementVisible('css', '[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"] [data-drupal-selector="edit-action"]');

    // The AI Automator plugin should now be available after the automator is
    // configured.
    $this->assertNotNull($select->find('css', 'option[value="automator_autocomplete_tags_on_taxonomy"]'));

    // With every allowed action now available, the message should be gone.
    $assertSession->pageTextNotContains('Some of the actions are unavailable for the current field.');
  }

  /**
   * Tests the warning shown when a configured action becomes unavailable.
   */
  public function testConfiguredActionBecomesUnavailable() {
    // Make the automator action available, then enable it on the form display.
    $this->createContentTagsAutomator();
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent('field_tags', [
      'type' => 'entity_reference_autocomplete_tags',
      'third_party_settings' => [
        'field_widget_actions' => [
          'select-uuid-1234' => [
            'enabled' => '1',
            'button_label' => 'Fill',
            'multiple' => '1',
            'plugin_id' => 'fill_entity_reference_test_action',
          ],
          'automator-uuid-5678' => [
            'enabled' => '1',
            'button_label' => 'Suggest with AI',
            'multiple' => '1',
            'plugin_id' => 'automator_autocomplete_tags_on_taxonomy',
          ],
        ],
      ],
    ])->save();

    // Remove the automator: the configured action is now unavailable.
    \Drupal::entityTypeManager()->getStorage('ai_automator')
      ->load('node.page.field_content_tags.default')->delete();

    $assertSession = $this->assertSession();
    $this->drupalGet('admin/structure/types/manage/' . $this->type . '/form-display');
    $this->click('[data-drupal-selector="edit-fields-field-tags-settings-edit"]');
    $assertSession->waitForElementVisible('css', '[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"]');
    $this->click('[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"] summary');
    $assertSession->waitForElementVisible('css', '[data-drupal-selector="edit-fields-field-tags-settings-edit-form-third-party-settings-field-widget-actions"]');

    $assertSession->pageTextContains('A configured action is currently unavailable and has been hidden.');
  }

  /**
   * Creates an AI Automator on the field_tags field.
   *
   * @return \Drupal\ai_automators\Entity\AiAutomator
   *   The saved automator entity.
   */
  protected function createContentTagsAutomator(): AiAutomator {
    $automator = AiAutomator::create([
      'id' => 'node.page.field_content_tags.default',
      'label' => 'Content Tags Classification',
      'rule' => 'llm_taxonomy',
      'input_mode' => 'token',
      'weight' => 100,
      'worker_type' => 'direct',
      'entity_type' => 'node',
      'bundle' => $this->type,
      'field_name' => 'field_tags',
      'edit_mode' => FALSE,
      'base_field' => 'body',
      'prompt' => "Based on the context text choose up to {{ max_amount }} categories from the category context that fits the text.\r\n\r\nCategory options:\r\n{{ value_options_comma }}\r\n\r\nContext:\r\n{{ context }}\r\n",
      'token' => "Based on the context text choose up to 5 categories from the category context that fits the text.\r\n\r\nContext:\r\n[node:body]\r\n",
      'plugin_config' => [
        'automator_enabled' => 1,
        'automator_rule' => 'llm_taxonomy',
        'automator_mode' => 'token',
        'automator_base_field' => 'body',
        'automator_prompt' => "Based on the context text choose up to {{ max_amount }} categories from the category context that fits the text.\r\n\r\nCategory options:\r\n{{ value_options_comma }}\r\n\r\nContext:\r\n{{ context }}\r\n",
        'automator_token' => "Based on the context text choose up to 5 categories from the category context that fits the text.\r\n\r\nContext:\r\n[node:render:full]\r\n",
        'automator_edit_mode' => 0,
        'automator_label' => 'Content Tags Classification',
        'automator_weight' => '100',
        'automator_worker_type' => 'direct',
        'automator_ai_provider' => 'default_json',
        'automator_clean_up' => '',
        'automator_search_similar_tags' => 0,
      ],
    ]);
    $automator->save();
    return $automator;
  }

  /**
   * Tests the FillSelectCommand using Taxonomy terms with per-item actions.
   *
   * A per-item select is wrapped by the module, so its rendered name is
   * 'field_tags[widget][]'.
   */
  public function testFillEntityReferenceSelectAjaxCommand() {
    $this->assertFillEntityReference('1', 'field_tags[widget][]');
  }

  /**
   * Tests the FillSelectCommand using Taxonomy terms with whole-field actions.
   *
   * A whole-field select is not wrapped, so its rendered name is
   * 'field_tags[]'. The test action derives the selector from the resolved
   * target element, so this only passes when the lookup returns the select.
   */
  public function testFillEntityReferenceSelectWholeFieldAjaxCommand() {
    $this->assertFillEntityReference('0', 'field_tags[]');
  }

  /**
   * Fills the entity reference field through each widget and checks the DOM.
   *
   * @param string $multiple
   *   The 'multiple' action setting: '1' for per-item, '0' for whole-field.
   * @param string $select_name
   *   The rendered name of the select for the options and tagify widgets.
   */
  protected function assertFillEntityReference(string $multiple, string $select_name): void {
    $field_widgets = [
      'options_select',
      'tagify_select_widget',
      'entity_reference_autocomplete_tags',
    ];

    foreach ($field_widgets as $field_widget) {
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
      $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');

      // Configure with standard options_select first.
      $form_display->setComponent('field_tags', [
        'type' => $field_widget,
        'third_party_settings' => [
          'field_widget_actions' => [
            'select-uuid-1234' => [
              'enabled' => '1',
              'button_label' => 'Fill',
              'multiple' => $multiple,
              'plugin_id' => 'fill_entity_reference_test_action',
            ],
          ],
        ],
      ])->save();

      $this->drupalGet('node/add/' . $this->type);
      $assertSession = $this->assertSession();

      // Click the "Fill Select" button.
      $this->click('.field--name-field-tags .field-widget-action-fill_entity_reference_test_action');

      // Click the Insert button after the dialog form window loads.
      $insert_button = $assertSession->waitForElementVisible('css', '.ui-dialog-buttonpane .form-submit');
      $this->assertNotNull($insert_button, "Insert button not found for widget: $field_widget");
      $insert_button->press();
      $assertSession->assertWaitOnAjaxRequest();

      switch ($field_widget) {
        case 'options_select':
        case 'tagify_select_widget':
          $assertSession->waitForElement('css', 'select[name="' . $select_name . '"] option[value="' . $this->terms[1] . '"]:checked');
          $field = $this->getSession()->getPage()->findField($select_name);
          $this->assertNotNull($field, "Could not find select list for widget: $field_widget");

          $selected_values = $field->getValue();
          $this->assertContains((string) $this->terms[1], $selected_values, "Term 1 ID missing in $field_widget");
          $this->assertContains((string) $this->terms[3], $selected_values, "Term 3 ID missing in $field_widget");
          $this->assertNotContains((string) $this->terms[2], $selected_values, "Term 2 ID incorrectly present in $field_widget");
          break;

        case 'entity_reference_autocomplete_tags':
          $assertSession->waitForElement('css', 'input[name="field_tags[target_id]"][value*="Term 1"]');
          $field = $this->getSession()->getPage()->findField('field_tags[target_id]');
          $this->assertNotNull($field, "Could not find autocomplete input for widget: $field_widget");

          $value = $field->getValue();
          $this->assertStringContainsString("Term 1 ({$this->terms[1]})", $value, "Term 1 missing in $field_widget");
          $this->assertStringContainsString("Term 3 ({$this->terms[3]})", $value, "Term 3 missing in $field_widget");
          $this->assertStringNotContainsString("Term 2", $value, "Term 2 incorrectly present in $field_widget");
          break;
      }
    }
  }

}
