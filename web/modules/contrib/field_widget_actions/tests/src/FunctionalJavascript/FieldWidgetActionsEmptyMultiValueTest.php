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
 * Tests building the entity from an empty multi-value field on the action path.
 *
 * An empty multi-value widget can submit a literal NULL at the field's value
 * path; core's WidgetBase::extractFormValues() then calls
 * massageFormValues(NULL), which fails its array type hint. A direct-fill
 * action that builds the entity must not crash in that case.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsEmptyMultiValueTest extends WebDriverTestBase {

  /**
   * Field Widget Action config schema validation on entity-ref is a known gap.
   *
   * @var bool
   *
   * @see \Drupal\Tests\field_widget_actions\FunctionalJavascript\FieldWidgetActionsUiEntityReferenceSelectTest
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'options',
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
      'administer taxonomy',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    $type = $this->drupalCreateContentType(['type' => 'test_page']);
    $this->type = $type->id();

    $vocabulary = Vocabulary::create(['vid' => 'tags', 'name' => 'Tags']);
    $vocabulary->save();
    for ($i = 1; $i <= 3; $i++) {
      Term::create(['name' => "Term $i", 'vid' => $vocabulary->id()])->save();
    }

    // Multi-value (cardinality -1) entity-reference field.
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
        'handler_settings' => ['target_bundles' => ['tags' => 'tags']],
      ],
    ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');
    $form_display->setComponent('field_tags', [
      'type' => 'options_select',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-emv' => [
            'enabled' => '1',
            'button_label' => 'Build entity',
            'multiple' => '0',
            'weight' => '0',
            'plugin_id' => 'build_entity_direct_fill',
          ],
        ],
      ],
    ]);
    $form_display->save();
  }

  /**
   * Building the entity must not crash when the multi-value field is empty.
   */
  public function testBuildEntityWithEmptyMultiValueField() {
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // The multi-value select is present and nothing is selected.
    $assertSession->elementExists('css', '.field--name-field-tags select');

    // Click the direct-fill action — it builds the entity from the submitted
    // (empty) values. This must not 500 with a massageFormValues TypeError.
    $this->click('.field--name-field-tags .field-widget-action-build_entity_direct_fill');
    $assertSession->assertWaitOnAjaxRequest();

    // The callback ran to completion and reported success.
    $assertSession->pageTextContains('Entity built without error.');
  }

}
