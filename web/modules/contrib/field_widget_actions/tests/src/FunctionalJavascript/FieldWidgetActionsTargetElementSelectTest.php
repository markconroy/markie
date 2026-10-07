<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests filling a select widget from the element the lookup resolves to.
 *
 * The 'resolve_target_test_action' derives its selector and its fill command
 * from FieldWidgetActionBase::getTargetElement() alone. With the previous
 * lookup that element was empty for select widgets, so no command was sent
 * and the select stayed untouched; these tests fail in that state.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsTargetElementSelectTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
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
   */
  protected string $type;

  /**
   * The taxonomy term IDs keyed by creation order (1..5).
   *
   * @var array<int, int>
   */
  protected array $terms = [];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'bypass node access',
    ]));

    $this->type = $this->drupalCreateContentType(['type' => 'test_page'])->id();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    for ($i = 1; $i <= 5; $i++) {
      $term = Term::create(['name' => "Term $i", 'vid' => 'tags']);
      $term->save();
      $this->terms[$i] = (int) $term->id();
    }
  }

  /**
   * Creates a field with an options_select widget and the test action.
   *
   * @param string $field_name
   *   The field name.
   * @param array $storage
   *   Field storage values (type, settings, cardinality).
   * @param array $field
   *   Additional field config values.
   * @param bool $multiple
   *   Whether the action is per-item (TRUE) or whole-field (FALSE).
   */
  protected function createSelectField(string $field_name, array $storage, array $field, bool $multiple): void {
    FieldStorageConfig::create($storage + [
      'field_name' => $field_name,
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create($field + [
      'field_storage' => FieldStorageConfig::loadByName('node', $field_name),
      'bundle' => $this->type,
      'label' => $field_name,
    ])->save();

    EntityFormDisplay::load('node.' . $this->type . '.default')
      ->setComponent($field_name, [
        'type' => 'options_select',
        'third_party_settings' => [
          'field_widget_actions' => [
            'resolve-uuid-1234' => [
              'enabled' => TRUE,
              'button_label' => 'Fill',
              'multiple' => $multiple,
              'plugin_id' => 'resolve_target_test_action',
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * Creates the multi-value taxonomy reference field.
   *
   * @param bool $multiple
   *   Whether the action is per-item (TRUE) or whole-field (FALSE).
   */
  protected function createTagsField(bool $multiple): void {
    $this->createSelectField('field_tags', [
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ], [
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['tags' => 'tags']],
      ],
    ], $multiple);
  }

  /**
   * Clicks the action button and waits for the AJAX round trip.
   *
   * @param string $field_name
   *   The field whose button is clicked.
   */
  protected function clickFill(string $field_name): void {
    $this->drupalGet('node/add/' . $this->type);
    $this->click('.field--name-' . str_replace('_', '-', $field_name) . ' .field-widget-action-resolve_target_test_action');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Asserts the selected values of a multiple select.
   *
   * @param string $name
   *   The rendered name of the select.
   * @param int[] $expected
   *   The term IDs that must be selected; every other term must not be.
   */
  protected function assertSelectedTerms(string $name, array $expected): void {
    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', 'select[name="' . $name . '"] option[value="' . $expected[0] . '"]:checked');
    $select = $this->getSession()->getPage()->findField($name);
    $this->assertNotNull($select, "Select $name not found.");
    $selected = array_map('intval', (array) $select->getValue());
    sort($selected);
    $this->assertSame($expected, $selected);
  }

  /**
   * A per-item action on a multi-value select fills the wrapped select.
   *
   * The module wraps a per-item select in a container, so the rendered name
   * is 'field_tags[widget][]'.
   */
  public function testPerItemSelectIsFilled(): void {
    $this->createTagsField(TRUE);
    $this->clickFill('field_tags');
    $this->assertSelectedTerms('field_tags[widget][]', [$this->terms[1], $this->terms[2]]);
  }

  /**
   * A whole-field action on a multi-value select fills the plain select.
   *
   * Without the wrapper the rendered name is 'field_tags[]'.
   */
  public function testWholeFieldSelectIsFilled(): void {
    $this->createTagsField(FALSE);
    $this->clickFill('field_tags');
    $this->assertSelectedTerms('field_tags[]', [$this->terms[1], $this->terms[2]]);
  }

  /**
   * A single-value list select is filled with the first option.
   *
   * Actions default to per-item mode, which is what a single-cardinality
   * field gets out of the box.
   */
  public function testSingleListSelectIsFilled(): void {
    $this->createSelectField('field_list', [
      'type' => 'list_string',
      'settings' => ['allowed_values' => ['one' => 'One', 'two' => 'Two', 'three' => 'Three']],
      'cardinality' => 1,
    ], [], TRUE);
    $this->clickFill('field_list');

    $assert_session = $this->assertSession();
    $assert_session->waitForElement('css', 'select[name="field_list[widget]"] option[value="one"]:checked');
    $select = $this->getSession()->getPage()->findField('field_list[widget]');
    $this->assertNotNull($select, 'Select field_list[widget] not found.');
    $this->assertSame('one', $select->getValue());
  }

}
