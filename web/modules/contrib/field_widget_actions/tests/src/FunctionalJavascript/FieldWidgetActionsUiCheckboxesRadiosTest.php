<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional JS tests for the FillCheckboxesOrRadiosCommand AJAX command.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsUiCheckboxesRadiosTest extends WebDriverTestBase {

  /**
   * General field widget action schema validation disabled.
   *
   * @var bool
   *
   * @todo This is unrelated to the checkboxes/radios tests, and is a general
   * Field Widget Action schema validation issue. Resolve this in a follow-up.
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * The node type id.
   *
   * @var string
   */
  protected string $type;

  /**
   * The taxonomy vocabulary.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected Vocabulary $vocabulary;

  /**
   * The taxonomy term IDs keyed by creation order (1..5).
   *
   * @var array<int, int>
   */
  protected array $terms = [];

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

    $type = $this->drupalCreateContentType(['type' => 'test_page']);
    $this->type = $type->id();

    $this->vocabulary = Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);
    $this->vocabulary->save();

    for ($i = 1; $i <= 5; $i++) {
      $term = Term::create([
        'name' => "Term $i",
        'vid' => $this->vocabulary->id(),
      ]);
      $term->save();
      $this->terms[$i] = $term->id();
    }
  }

  /**
   * Creates and attaches the field_tags entity reference field.
   *
   * @param int $cardinality
   *   The field cardinality: -1 for unlimited (renders as checkboxes), 1 for
   *   single-value (renders as radio buttons).
   */
  protected function createTagsField(int $cardinality): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => $cardinality,
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

    EntityFormDisplay::load('node.' . $this->type . '.default')
      ->setComponent('field_tags', [
        'type' => 'options_buttons',
        'third_party_settings' => [
          'field_widget_actions' => [
            'test-uuid-1234' => [
              'enabled' => '1',
              'button_label' => 'Fill',
              'multiple' => '0',
              'plugin_id' => 'fill_checkboxes_or_radios_test_action',
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * Tests filling checkboxes for an unlimited-cardinality entity reference.
   *
   * The options_buttons widget renders checkboxes when cardinality > 1.
   * The FillCheckboxesOrRadiosCommand should check the checkboxes for the
   * supplied entity IDs and leave others unchecked.
   */
  public function testFillCheckboxes(): void {
    $this->createTagsField(-1);

    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    $this->click('.field--name-field-tags .field-widget-action-fill_checkboxes_or_radios_test_action');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Click to confirm insert.');

    $page = $this->getSession()->getPage();
    $insert_button = $page->find('css', '.ui-dialog-buttonpane .form-submit');
    $this->assertNotNull($insert_button, 'Insert button not found.');
    $insert_button->press();
    $assertSession->assertWaitOnAjaxRequest();

    // Wait for term 1 checkbox to become checked.
    $assertSession->waitForElement('css', 'input[type="checkbox"][name="field_tags[' . $this->terms[1] . ']"]:checked');

    // Term 1 and term 3 should be checked.
    $checkbox_term1 = $page->find('css', 'input[type="checkbox"][name="field_tags[' . $this->terms[1] . ']"]');
    $this->assertNotNull($checkbox_term1, 'Checkbox for term 1 not found.');
    $this->assertTrue($checkbox_term1->isChecked(), 'Checkbox for term 1 should be checked.');

    $checkbox_term3 = $page->find('css', 'input[type="checkbox"][name="field_tags[' . $this->terms[3] . ']"]');
    $this->assertNotNull($checkbox_term3, 'Checkbox for term 3 not found.');
    $this->assertTrue($checkbox_term3->isChecked(), 'Checkbox for term 3 should be checked.');

    // Term 2 should not be checked.
    $checkbox_term2 = $page->find('css', 'input[type="checkbox"][name="field_tags[' . $this->terms[2] . ']"]');
    $this->assertNotNull($checkbox_term2, 'Checkbox for term 2 not found.');
    $this->assertFalse($checkbox_term2->isChecked(), 'Checkbox for term 2 should not be checked.');
  }

  /**
   * Tests filling radio buttons for a single-cardinality entity reference.
   *
   * The options_buttons widget renders radio buttons when cardinality = 1.
   * The FillCheckboxesOrRadiosCommand should select the radio button for the
   * first supplied entity ID.
   */
  public function testFillRadios(): void {
    $this->createTagsField(1);

    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    $this->click('.field--name-field-tags .field-widget-action-fill_checkboxes_or_radios_test_action');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Click to confirm insert.');

    $page = $this->getSession()->getPage();
    $insert_button = $page->find('css', '.ui-dialog-buttonpane .form-submit');
    $this->assertNotNull($insert_button, 'Insert button not found.');
    $insert_button->press();
    $assertSession->assertWaitOnAjaxRequest();

    // Wait for term 1 radio to become selected.
    $assertSession->waitForElement('css', 'input[type="radio"][name="field_tags"][value="' . $this->terms[1] . '"]:checked');

    // Term 1 (first supplied value) should be selected.
    $radio_term1 = $page->find('css', 'input[type="radio"][name="field_tags"][value="' . $this->terms[1] . '"]');
    $this->assertNotNull($radio_term1, 'Radio button for term 1 not found.');
    $this->assertTrue($radio_term1->isChecked(), 'Radio button for term 1 should be selected.');

    // Term 2 should not be selected.
    $radio_term2 = $page->find('css', 'input[type="radio"][name="field_tags"][value="' . $this->terms[2] . '"]');
    $this->assertNotNull($radio_term2, 'Radio button for term 2 not found.');
    $this->assertFalse($radio_term2->isChecked(), 'Radio button for term 2 should not be selected.');
  }

}
