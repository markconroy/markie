<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\Role;

/**
 * Tests tracking of config entities.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class ConfigEntityTrackingTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'views_ui',
    'webform',
    'block',
    'block_content',
    'block_field',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access entity usage statistics',
      'administer blocks',
      'administer block content',
      'administer entity usage',
      'administer views',
      'administer webform',
    ]);

  }

  /**
   * Tests webform tracking.
   */
  public function testWebformTracking(): void {

    // Create an entity reference field pointing to a webform.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_eu_test_related_webforms',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'webform',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'eu_test_ct',
      'entity_type' => 'node',
      'field_name' => 'field_eu_test_related_webforms',
      'label' => 'Related Webforms',
      'settings' => [
        'handler' => 'default:webform',
        'handler_settings' => [
          'target_bundles' => NULL,
          'auto_create' => FALSE,
        ],
      ],
    ])->save();

    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_webforms', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_webforms', [
        'type' => 'entity_reference_label',
      ])
      ->save();

    $this->drupalPlaceBlock('local_tasks_block');
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Check some config-entity related settings on the config form.
    $this->drupalGet('/admin/config/entity-usage/settings');

    // We should have an unchecked checkbox for a local tab.
    $webform_tab_checkbox = $assert_session->fieldExists('local_task_enabled_entity_types[entity_types][webform]');
    $assert_session->checkboxNotChecked('local_task_enabled_entity_types[entity_types][webform]');

    // Check it so we can test it later.
    $webform_tab_checkbox->click();

    // We should have an unchecked checkbox for target entity type.
    $targets_fieldset_wrapper = $assert_session->elementExists('css', '#edit-track-enabled-target-entity-types summary');
    $targets_fieldset_wrapper->click();
    $webform_target_checkbox = $assert_session->fieldExists('track_enabled_target_entity_types[entity_types][webform]');
    $assert_session->checkboxNotChecked('track_enabled_target_entity_types[entity_types][webform]');

    // Check tracking webforms as targets.
    $webform_target_checkbox->click();

    // Save configuration.
    $page->pressButton('Save configuration');
    $this->saveHtmlOutput();

    // Make sure the 'contact' webform exists.
    $this->drupalGet('/form/contact');
    $page->findField('email');
    $page->findButton('Send message');

    // Create a node referencing this webform.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node that points to a webform');
    $page->fillField('field_eu_test_related_webforms[0][target_id]', 'Contact (contact)');
    $page->pressButton('Save');
    $this->saveHtmlOutput();
    $this->assertSession()->pageTextContains('Entity Usage test content Node that points to a webform has been created.');

    // Visit the webform page, check the usage tab is there.
    $this->clickLink('Contact');
    $this->saveHtmlOutput();

    // Click on the tab and verify if the usage was correctly tracked.
    $assert_session->pageTextContains('Usage');
    $page->clickLink('Usage');
    $this->saveHtmlOutput();
    // We should be at /webform/contact/usage.
    $this->assertStringContainsString("/webform/contact/usage", $session->getCurrentUrl());
    $assert_session->elementContains('css', 'main table', 'Node that points to a webform');
    $assert_session->elementContains('css', 'main table', 'Related Webforms');
  }

  /**
   * Tests block_field / views tracking.
   */
  public function testBlockFieldViewsTracking(): void {

    // Create block field on the node type.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_eu_test_related_views',
      'entity_type' => 'node',
      'type' => 'block_field',
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'eu_test_ct',
      'entity_type' => 'node',
      'field_name' => 'field_eu_test_related_views',
      'label' => 'Related Views',
    ])->save();

    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_views', [
        'type' => 'block_field_default',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_views', [
        'type' => 'block_field',
      ])
      ->save();

    $this->drupalPlaceBlock('local_tasks_block');
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Check some config-entity related settings on the config form.
    $this->drupalGet('/admin/config/entity-usage/settings');

    // We should have an unchecked checkbox for target entity type.
    $targets_fieldset_wrapper = $assert_session->elementExists('css', '#edit-track-enabled-target-entity-types summary');
    $targets_fieldset_wrapper->click();
    $view_target_checkbox = $assert_session->fieldExists('track_enabled_target_entity_types[entity_types][view]');
    $assert_session->checkboxNotChecked('track_enabled_target_entity_types[entity_types][view]');

    // Check tracking views as targets.
    $view_target_checkbox->click();

    // Also allow views to have the usage tab visible.
    $views_tab_checkbox = $assert_session->fieldExists('local_task_enabled_entity_types[entity_types][view]');
    $views_tab_checkbox->click();

    // Save configuration.
    $page->pressButton('Save configuration');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Make sure our target view exists.
    $view_name = 'content_recent';
    $view = \Drupal::entityTypeManager()->getStorage('view')->load($view_name);
    $this->assertNotNull($view);

    // Create a node referencing this view through a Block Field field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node that points to a block with a view');
    $assert_session->optionExists('field_eu_test_related_views[0][plugin_id]', "views_block:{$view_name}-block_1");
    $page->selectFieldOption('field_eu_test_related_views[0][plugin_id]', "views_block:{$view_name}-block_1");
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $page->pressButton('Save');
    $this->saveHtmlOutput();
    $this->assertSession()->pageTextContains('Entity Usage test content Node that points to a block with a view has been created.');
    /** @var \Drupal\node\NodeInterface $host_node */
    $host_node = $this->getLastEntityOfType('node', TRUE);

    // Check that usage for this view is correctly tracked.
    $usage = \Drupal::service('entity_usage.usage')->listSources($view);
    $expected = [
      'node' => [
        $host_node->id() => [
          [
            'source_langcode' => $host_node->language()->getId(),
            'source_vid' => $host_node->getRevisionId(),
            'method' => 'block_field',
            'field_name' => 'field_eu_test_related_views',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // We should also be able to see the usage tab and usage page.
    $this->drupalGet('/admin/structure/views/view/content_recent');
    $assert_session->linkExists('Usage');
    $this->drupalGet('/admin/structure/views/view/content_recent/usage');
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($host_node->label(), $first_row_title_link->getText());
    $this->assertStringContainsString($host_node->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Related Views', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published', $first_row_status->getText());
  }

  /**
   * Tests block_field / custom_blocks tracking.
   */
  public function testBlockFieldCustomBlocksTracking(): void {

    // Create block field on the node type.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_eu_test_related_blocks',
      'entity_type' => 'node',
      'type' => 'block_field',
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'eu_test_ct',
      'entity_type' => 'node',
      'field_name' => 'field_eu_test_related_blocks',
      'label' => 'Related Blocks',
    ])->save();

    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_blocks', [
        'type' => 'block_field_default',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_blocks', [
        'type' => 'block_field',
      ])
      ->save();

    $this->drupalPlaceBlock('local_tasks_block');
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Check some config-entity related settings on the config form.
    $this->drupalGet('/admin/config/entity-usage/settings');

    // We should have a checked checkbox for source/target entity type.
    $sources_fieldset_wrapper = $assert_session->elementExists('css', '#edit-track-enabled-source-entity-types summary');
    $sources_fieldset_wrapper->click();
    $assert_session->fieldExists('track_enabled_source_entity_types[entity_types][block_content]');
    $assert_session->checkboxChecked('track_enabled_source_entity_types[entity_types][block_content]');
    $targets_fieldset_wrapper = $assert_session->elementExists('css', '#edit-track-enabled-target-entity-types summary');
    $targets_fieldset_wrapper->click();
    $assert_session->checkboxChecked('track_enabled_target_entity_types[entity_types][block_content]');

    // Also allow views to have the usage tab visible.
    $block_tab_checkbox = $assert_session->fieldExists('local_task_enabled_entity_types[entity_types][block_content]');
    $block_tab_checkbox->click();

    // Save configuration.
    $page->pressButton('Save configuration');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Create a new target content block.
    BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
      'revision' => TRUE,
    ]);
    $block_content = BlockContent::create([
      'info' => 'My first custom block',
      'type' => 'basic',
      'langcode' => 'en',
    ]);
    $block_content->save();

    // Create a node referencing this block through a Block Field field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node that points to a custom block');
    $assert_session->optionExists('field_eu_test_related_blocks[0][plugin_id]', "block_content:{$block_content->uuid()}");
    $page->selectFieldOption('field_eu_test_related_blocks[0][plugin_id]', "block_content:{$block_content->uuid()}");
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $page->pressButton('Save');
    $this->saveHtmlOutput();
    $this->assertSession()->pageTextContains('Entity Usage test content Node that points to a custom block has been created.');
    /** @var \Drupal\node\NodeInterface $host_node */
    $host_node = $this->getLastEntityOfType('node', TRUE);

    // Check that usage for this block is correctly tracked.
    $usage = \Drupal::service('entity_usage.usage')->listSources($block_content);
    $expected = [
      'node' => [
        $host_node->id() => [
          [
            'source_langcode' => $host_node->language()->getId(),
            'source_vid' => $host_node->getRevisionId(),
            'method' => 'block_field',
            'field_name' => 'field_eu_test_related_blocks',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // We should also be able to get to the usage page from the block page.
    $this->drupalGet($block_content->toUrl());
    $this->clickLink('Usage');
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($host_node->label(), $first_row_title_link->getText());
    $this->assertStringContainsString($host_node->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Related Blocks', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published', $first_row_status->getText());
  }

}
