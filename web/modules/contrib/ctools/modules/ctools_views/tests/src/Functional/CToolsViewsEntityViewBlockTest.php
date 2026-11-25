<?php

namespace Drupal\Tests\ctools_views\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\views_ui\Functional\UITestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;

// Workaround to support tests against Drupal 10.1.x and below.
// @todo Remove once we end support for Drupal 10.1.x and below.
if (!trait_exists(EntityReferenceFieldCreationTrait::class)) {
  class_alias('\Drupal\Tests\field\Traits\EntityReferenceTestTrait', EntityReferenceFieldCreationTrait::class);
}

/**
 * Tests the ctools_views block display plugin overriding entity View filters.
 *
 * @group ctools_views
 * @see \Drupal\ctools_views\Plugin\Display\Block
 */
#[Group('ctools_views')]
#[RunTestsInSeparateProcesses]
class CToolsViewsEntityViewBlockTest extends UITestBase {

  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ctools_views',
    'ctools_views_test_views',
    'taxonomy',
    'options',
    'datetime',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['ctools_views_entity_test'];

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * The node entities used by the test.
   *
   * @var array
   */
  protected $entities = [];

  /**
   * The taxonomy_term entities used by the test.
   *
   * @var array
   */
  protected $terms = [];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'ctools_views',
      'name' => 'Ctools views',
    ]);

    // Create test textfield.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_ctools_views_text',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_ctools_views_text',
      'bundle' => 'ctools_views',
      'label' => 'Ctools Views test textfield',
      'translatable' => FALSE,
    ])->save();

    // Create a vocabulary named "Tags".
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'tags',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $vocabulary->save();
    $this->terms[] = $this->createTerm($vocabulary);
    $this->terms[] = $this->createTerm($vocabulary);
    $this->terms[] = $this->createTerm($vocabulary);

    $handler_settings = [
      'target_bundles' => [
        $vocabulary->id() => $vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', 'ctools_views', 'field_ctools_views_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Create list field.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_ctools_views_list',
      'type' => 'list_string',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'allowed_values' => [
          'item1' => "Item 1",
          'item2' => "Item 2",
          'item3' => "Item 3",
        ],
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_ctools_views_list',
      'bundle' => 'ctools_views',
      'label' => 'Ctools Views List',
      'translatable' => FALSE,
    ])->save();

    // Create date field.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_ctools_views_date',
      'type' => 'datetime',
      'cardinality' => 1,
      'settings' => [
        'datetime_type' => 'date',
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_ctools_views_date',
      'bundle' => 'ctools_views',
      'label' => 'Ctools Views Date',
      'translatable' => FALSE,
    ])->save();

    ViewTestData::createTestViews(get_class($this), ['ctools_views_test_views']);
    // @phpstan-ignore-next-line Storing is practical for many call sites.
    $this->storage = $this->container->get('entity_type.manager')->getStorage('block');

    foreach ($this->testNodes() as $values) {
      $entity = Node::create($values);
      $entity->save();
      $this->entities[] = $entity;
    }
  }

  /**
   * Test ctools_views 'configure_filters' configuration with text field values.
   */
  public function testConfigureFiltersTextfield() {
    $default_theme = $this->config('system.theme')->get('default');
    $filter_id = 'field_ctools_views_text_value';
    $filter_op_id = $filter_id . '_op';

    $block = [];
    $block['id'] = 'views_block:ctools_views_entity_test-block_filter_text';
    $block['region'] = 'sidebar_first';
    $block['theme'] = $this->config('system.theme')->get('default');

    // Get the "Configure block" form for our Views block.
    $this->drupalGet('admin/structure/block/add/' . $block['id'] . '/' . $block['theme']);
    $this->assertSession()->fieldExists('settings[exposed][filter-' . $filter_id . '][' . $filter_id . '_wrapper][' . $filter_id . ']');
    $this->assertSession()->fieldExists('settings[exposed][filter-' . $filter_id . '][' . $filter_id . '_wrapper][' . $filter_op_id . ']');

    // Add block to sidebar_first region with default settings.
    $this->submitForm([
      'region' => $block['region'],
      'id' => 'views_block__ctools_views_entity_test_block_filter_text',
    ], 'Save block');
    // @todo Remove this after debugging.
    $this->assertSession()->pageTextContains('The block configuration has been saved.');

    // Assert configure_filters default settings.
    $this->drupalGet('<front>');
    $this->assertSession()->fieldNotExists($filter_id);
    $this->assertSession()->fieldNotExists($filter_op_id);
    $this->assertSession()->buttonNotExists('Apply');
    $this->assertSession()->elementNotExists('xpath', '//fieldset[@id="edit-field-ctools-views-text-value-wrapper"]');

    // @todo Remove this after debugging.
    $this->assertSession()->elementExists('xpath', '//div');
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text")]//table', 1);

    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text")]//table/tbody/tr', 3);
    $this->assertSession()->fieldNotExists($filter_id);

    // Override configure_filters settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-' . $filter_id . '][' . $filter_id . '_wrapper][' . $filter_id . ']'] = 'text_1';
    $edit['settings[exposed][filter-' . $filter_id . '][exposed]'] = '1';
    $edit['settings[exposed][filter-' . $filter_id . '][use_operator]'] = '1';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_text');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_text');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals('text_1', $config['exposed']['filter-' . $filter_id]['value'], "'configure_filters' value is properly saved.");
    $this->assertEquals('=', $config['exposed']['filter-' . $filter_id]['operator'], "'configure_filters' operator is properly saved.");
    $this->assertEquals('string', $config['exposed']['filter-' . $filter_id]['plugin_id'], "'configure_filters' plugin_id is properly saved.");

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    $this->assertSession()->fieldExists($filter_id);
    $this->assertSession()->fieldExists($filter_op_id);
    $this->assertSession()->buttonExists('Apply');
    $this->assertSession()->elementExists('xpath', '//fieldset[@id="edit-field-ctools-views-text-value-wrapper"]');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text")]//table/tbody/tr', 2);

    // Override operator setting.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-' . $filter_id . '][' . $filter_id . '_wrapper][' . $filter_id . ']'] = 'text_1';
    $edit['settings[exposed][filter-' . $filter_id . '][' . $filter_id . '_wrapper][' . $filter_op_id . ']'] = '!=';
    $edit['settings[exposed][filter-' . $filter_id . '][exposed]'] = '1';
    $edit['settings[exposed][filter-' . $filter_id . '][use_operator]'] = '0';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_text');
    $this->submitForm($edit, 'Save block');

    // Check that operator was saved.
    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_text');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals('!=', $config['exposed']['filter-' . $filter_id]['operator'], "'configure_filters' operator is properly saved.");
    $this->assertEquals(TRUE, $config['exposed']['filter-' . $filter_id]['exposed'], "'configure_filters' exposed is properly saved.");
    $this->assertEquals(FALSE, $config['exposed']['filter-' . $filter_id]['expose']['use_operator'], "'configure_filters' exposed is properly saved.");

    // Assert overridden operator.
    $this->drupalGet('<front>');
    $this->assertSession()->fieldExists($filter_id);
    $this->assertSession()->fieldNotExists($filter_op_id);
    $this->assertSession()->buttonExists('Apply');
    $this->assertSession()->elementNotExists('xpath', '//fieldset[@id="edit-field-taxonomy-term-reference-target-id-wrapper"]');
    // Check that the overridden operator returns proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text")]//table/tbody/tr', 1);
  }

  /**
   * Test ctools_views 'configure_filters' with grouped text field.
   */
  public function testConfigureFiltersTextfieldGrouped() {
    $default_theme = $this->config('system.theme')->get('default');
    $filter_machine_name = 'title';
    $filter_id = 'non-default-identifier';
    $filter_op_id = $filter_id . '_op';

    $block = [];
    $block['id'] = 'views_block:ctools_views_entity_test-block_filter_text_grouped';
    $block['region'] = 'sidebar_first';
    $block['theme'] = $this->config('system.theme')->get('default');

    // Get the "Configure block" form for our Views block.
    $this->drupalGet('admin/structure/block/add/' . $block['id'] . '/' . $block['theme']);
    $this->assertSession()->fieldExists('settings[exposed][filter-' . $filter_machine_name . '][' . $filter_id . ']');

    // Add block to sidebar_first region with default settings.
    $this->submitForm([
      'region' => $block['region'],
      'id' => 'views_block__ctools_views_entity_test_block_filter_text_grouped',
    ], 'Save block');

    // Assert configure_filters default settings.
    $this->drupalGet('<front>');

    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr', 3);

    // Override configure_filters settings with test value contains group.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-' . $filter_machine_name . '][' . $filter_id . ']'] = '1';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_text_grouped');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_text_grouped');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals('1', $config['exposed']['filter-' . $filter_machine_name]['group_info']['default_group'], "'configure_filters' value is properly saved.");
    $this->assertEquals('string', $config['exposed']['filter-' . $filter_machine_name]['plugin_id'], "'configure_filters' plugin_id is properly saved.");

    // Check that the saved group is the default when editing the block again.
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_text_grouped');
    $this->assertSession()->fieldValueEquals('settings[exposed][filter-' . $filter_machine_name . '][' . $filter_id . ']', '1');

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr', 1);
    $this->assertSession()->elementTextEquals('xpath', '(//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr)[1]', 'Test entity 2');

    // Override configure_filters settings with test value not group.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-' . $filter_machine_name . '][' . $filter_id . ']'] = '2';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_text_grouped');
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    // Check that the overridden operator returns proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr', 2);
    $this->assertSession()->elementTextEquals('xpath', '(//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr)[1]', 'Test entity 1');
    $this->assertSession()->elementTextEquals('xpath', '(//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr)[2]', 'Test entity 2');

    // Override configure_filters settings with test value equals group.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-' . $filter_machine_name . '][' . $filter_id . ']'] = '3';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_text_grouped');
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    // Check that the overridden operator returns proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr', 1);
    $this->assertSession()->elementTextEquals('xpath', '(//div[contains(@class, "view-display-id-block_filter_text_grouped")]//table/tbody/tr)[1]', 'Test entity 1');
  }

  /**
   * Test ctools_views 'configure_filters' with taxonomy term field values.
   */
  public function testConfigureFiltersTaxonomy() {
    $default_theme = $this->config('system.theme')->get('default');
    $tid = $this->terms[0]->id();
    $term_label = $this->terms[0]->label();
    $filter_id = 'field_ctools_views_tags_target_id';
    $identifier = 'tags_filter_alternate_identifier';

    // Get the "Configure block" form for our Views block.
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_tax/' . $default_theme);
    $this->assertSession()->fieldExists('settings[exposed][filter-' . $filter_id . '][' . $identifier . ']');

    // Add block to sidebar_first region with default settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['id'] = 'views_block__ctools_views_entity_test_block_filter_tax';
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_tax/' . $default_theme);
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters default settings.
    $this->drupalGet('<front>');
    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_tax")]//table/tbody/tr', 3);

    // Override configure_filters settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-' . $filter_id . '][' . $identifier . ']'] = $tid;
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_tax');
    $this->submitForm($edit, 'Save block');

    // Test settings saved correctly.
    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_tax');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals([$tid => $tid], $config['exposed']['filter-field_ctools_views_tags_target_id']['value'], "'configure_filters' setting is properly saved.");

    // Test saved settings reload into configuration form correctly.
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_tax');
    $this->assertSession()->elementTextEquals('xpath', '//select[@data-drupal-selector="edit-settings-exposed-filter-field-ctools-views-tags-target-id-tags-filter-alternate-identifier"]/option[@selected="selected"]', $term_label);

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_tax")]//table/tbody/tr', 1);
  }

  /**
   * Test 'configure_filters' with a taxonomy select and an exposed operator.
   */
  public function testConfigureFiltersTaxonomyExposedOperator() {
    $default_theme = $this->config('system.theme')->get('default');
    $tid = $this->terms[0]->id();
    $filter_id = 'field_ctools_views_tags_target_id';
    $identifier = 'tags_filter_alternate_identifier';
    $wrapper = $identifier . '_wrapper';

    // Expose the operator, which wraps the operator and value elements in a
    // "{identifier}_wrapper" container.
    $view = $this->container->get('entity_type.manager')->getStorage('view')->load('ctools_views_entity_test');
    $display = &$view->getDisplay('block_filter_tax');
    $display['display_options']['filters'][$filter_id]['expose']['use_operator'] = TRUE;
    $view->save();

    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_tax/' . $default_theme);
    $this->assertSession()->fieldExists('settings[exposed][filter-' . $filter_id . '][' . $wrapper . '][' . $identifier . ']');
    $this->assertSession()->fieldExists('settings[exposed][filter-' . $filter_id . '][' . $wrapper . '][' . $filter_id . '_op]');

    // Add block to sidebar_first region with default settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['id'] = 'views_block__ctools_views_entity_test_block_filter_tax';
    $this->submitForm($edit, 'Save block');

    $this->drupalGet('<front>');
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_tax")]//table/tbody/tr', 3);

    // Override configure_filters settings.
    $edit = [];
    $edit['settings[exposed][filter-' . $filter_id . '][' . $wrapper . '][' . $identifier . ']'] = $tid;
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_tax');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_tax');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals([$tid => $tid], $config['exposed']['filter-' . $filter_id]['value'], "'configure_filters' setting is properly saved.");

    // Test saved settings reload into configuration form correctly.
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_tax');
    $this->assertSession()->elementTextEquals('xpath', '//select[@name="settings[exposed][filter-' . $filter_id . '][' . $wrapper . '][' . $identifier . ']"]/option[@selected="selected"]', $this->terms[0]->label());

    $this->drupalGet('<front>');
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_tax")]//table/tbody/tr', 1);
  }

  /**
   * Test ctools_views 'configure_filters' with taxonomy term autocomplete.
   */
  public function testConfigureFiltersTaxonomyAutocomplete() {
    $default_theme = $this->config('system.theme')->get('default');
    $tid = $this->terms[0]->id();
    $identifier = 'tags_autocomplete_alternate_identifier';

    // Get the "Configure block" form for our Views block.
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_auto/' . $default_theme);
    $this->assertSession()->fieldExists('settings[exposed][filter-field_ctools_views_tags_target_id][' . $identifier . ']');

    // Add block to sidebar_first region with default settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['id'] = 'views_block__ctools_views_entity_test_block_filter_auto';
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_auto/' . $default_theme);
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters default settings.
    $this->drupalGet('<front>');
    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_auto")]//table/tbody/tr', 3);

    // Override configure_filters settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $filter_term = $this->terms[0];
    $filter_value = EntityAutocomplete::getEntityLabels([$filter_term]);
    $edit['settings[exposed][filter-field_ctools_views_tags_target_id][' . $identifier . ']'] = $filter_value;
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_auto');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_auto');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals([$tid], $config['exposed']['filter-field_ctools_views_tags_target_id']['value'], "'configure_filters' setting is properly saved.");

    // Check rendered value of autosubmit field in reloaded form.
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_auto');
    $this->assertSession()->elementTextEquals('xpath', '//input[@data-drupal-selector="edit-settings-exposed-filter-field-ctools-views-tags-target-id-tags-autocomplete-alternate-identifier"]/@value', $filter_value);

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_auto")]//table/tbody/tr', 1);
  }

  /**
   * Test ctools_views 'configure_filters' configuration with list field values.
   */
  public function testConfigureFiltersList() {
    $default_theme = $this->config('system.theme')->get('default');

    // Get the "Configure block" form for our Views block.
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_list/' . $default_theme);
    $this->assertSession()->fieldExists('settings[exposed][filter-field_ctools_views_list_value][field_ctools_views_list_value]');

    // Add block to sidebar_first region with default settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['id'] = 'views_block__ctools_views_entity_test_block_filter_list';
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_list/' . $default_theme);
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters default settings.
    $this->drupalGet('<front>');
    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_list")]//table/tbody/tr', 3);

    // Override configure_filters settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-field_ctools_views_list_value][field_ctools_views_list_value]'] = 'item2';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_list');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_list');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals(['item2' => 'item2'], $config['exposed']['filter-field_ctools_views_list_value']['value'], "'configure_filters' setting is properly saved.");

    // Assert configure_filters overridden settings.
    $this->drupalGet('<front>');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_list")]//table/tbody/tr', 1);
  }

  /**
   * Test ctools_views 'configure_filters' configuration with date field values.
   */
  public function testConfigureFiltersDate() {
    $default_theme = $this->config('system.theme')->get('default');

    // Get the "Configure block" form for our Views block.
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_date/' . $default_theme);
    $this->assertSession()->fieldExists('settings[exposed][filter-field_ctools_views_date_value][field_ctools_views_date_value_wrapper][field_ctools_views_date_value][min]');
    $this->assertSession()->fieldExists('settings[exposed][filter-field_ctools_views_date_value][field_ctools_views_date_value_wrapper][field_ctools_views_date_value][max]');

    // Add block to sidebar_first region with default settings.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['id'] = 'views_block__ctools_views_entity_test_block_filter_date';
    $this->drupalGet('admin/structure/block/add/views_block:ctools_views_entity_test-block_filter_date/' . $default_theme);
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters default settings.
    $this->drupalGet('<front>');
    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_date")]//table/tbody/tr', 3);

    // Override configure_filters settings for between date filter.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-field_ctools_views_date_value][field_ctools_views_date_value_wrapper][field_ctools_views_date_value][min]'] = '2016-01-01';
    $edit['settings[exposed][filter-field_ctools_views_date_value][field_ctools_views_date_value_wrapper][field_ctools_views_date_value][max]'] = '2016-12-31';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_date');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_date');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals(['min' => '2016-01-01', 'max' => '2016-12-31'], $config['exposed']['filter-field_ctools_views_date_value']['value'], "'configure_filters' setting is properly saved.");

    // Assert overridden between date filter settings.
    $this->drupalGet('<front>');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_date")]//table/tbody/tr', 1);

    // Reset between date filter.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-field_ctools_views_date_value][field_ctools_views_date_value_wrapper][field_ctools_views_date_value][min]'] = '';
    $edit['settings[exposed][filter-field_ctools_views_date_value][field_ctools_views_date_value_wrapper][field_ctools_views_date_value][max]'] = '';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_date');
    $this->submitForm($edit, 'Save block');

    // Assert configure_filters reset/default settings.
    $this->drupalGet('<front>');
    // Check that the default settings return all results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_date")]//table/tbody/tr', 3);

    // Override configure_filters settings for greater than date filter.
    $edit = [];
    $edit['region'] = 'sidebar_first';
    $edit['settings[exposed][filter-field_ctools_views_date_value_greater][field_ctools_views_date_value_greater]'] = '2016-01-01';
    $this->drupalGet('admin/structure/block/manage/views_block__ctools_views_entity_test_block_filter_date');
    $this->submitForm($edit, 'Save block');

    $block = $this->storage->load('views_block__ctools_views_entity_test_block_filter_date');
    $config = $block->getPlugin()->getConfiguration();
    $this->assertEquals('2016-01-01', $config['exposed']['filter-field_ctools_views_date_value_greater']['value'], "'configure_filters' setting is properly saved.");

    // Assert overridden greater then date filter settings.
    $this->drupalGet('<front>');
    // Check that the overridden settings return proper results.
    $this->assertSession()->elementsCount('xpath', '//div[contains(@class, "view-display-id-block_filter_date")]//table/tbody/tr', 2);
  }

  /**
   * Add test content for this class's tests.
   */
  protected function testNodes(): array {
    return [
      [
        'type' => 'ctools_views',
        'title' => 'Test entity 1',
        'uid' => 1,
        'field_ctools_views_text' => [
          'value' => 'text_1',
          'format' => 'plain_text',
        ],
        'field_ctools_views_tags' => [
          'target_id' => $this->terms[0]->id(),
        ],
        'field_ctools_views_list' => [
          'value' => 'item1',
        ],
        'field_ctools_views_date' => [
          'value' => '1990-01-01',
        ],
      ],
      [
        'type' => 'ctools_views',
        'title' => 'Test entity 2',
        'uid' => 1,
        'field_ctools_views_text' => [
          'value' => 'text_2',
          'format' => 'plain_text',
        ],
        'field_ctools_views_tags' => [
          'target_id' => $this->terms[1]->id(),
        ],
        'field_ctools_views_list' => [
          'value' => 'item2',
        ],
        'field_ctools_views_date' => [
          'value' => '2016-10-04',
        ],
      ],
      [
        'type' => 'ctools_views',
        'title' => 'Test entity 3',
        'uid' => 0,
        'field_ctools_views_text' => [
          'value' => 'text_1',
          'format' => 'plain_text',
        ],
        'field_ctools_views_tags' => [
          'target_id' => $this->terms[2]->id(),
        ],
        'field_ctools_views_list' => [
          'value' => 'item3',
        ],
        'field_ctools_views_date' => [
          'value' => '2018-12-31',
        ],
      ],
    ];
  }

}
