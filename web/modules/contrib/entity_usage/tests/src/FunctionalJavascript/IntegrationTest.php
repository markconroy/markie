<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;

/**
 * Basic functional tests for the usage tracking.
 *
 * This will also implicitly test the Entity Reference and Link plugins.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class IntegrationTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $account = $this->drupalCreateUser([
      'administer node fields',
      'administer node display',
      'administer nodes',
      'bypass node access',
      'use text format eu_test_text_format',
      'administer entity usage',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests the tracking of nodes in some simple CRUD operations.
   */
  public function testCrudTracking(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create node 2 referencing node 1 using reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');
    $node2 = $this->getLastEntityOfType('node', TRUE);
    // Check that we correctly registered the relation between N2 and N1.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2->getRevisionId(),
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Open Node 1 edit and delete form and verify that no warning is present.
    $this->drupalGet("/node/{$node1->id()}/edit");
    $assert_session->pageTextNotContains('Modifications on this form will affect all existing usages of this entity');
    $this->drupalGet("/node/{$node1->id()}/delete");
    $assert_session->pageTextNotContains('There are recorded usages of this entity');
    // Configure nodes to have warning messages on both forms and try again.
    $this->drupalGet('/admin/config/entity-usage/settings');
    $edit_summary = $assert_session->elementExists('css', '#edit-edit-warning-message-entity-types summary');
    $edit_summary->click();
    $assert_session->pageTextContains('Entity types to show warning on edit form');
    $page->checkField('edit_warning_message_entity_types[entity_types][node]');
    $delete_summary = $assert_session->elementExists('css', '#edit-delete-warning-message-entity-types summary');
    $delete_summary->click();
    $assert_session->pageTextContains('Entity types to show warning on delete form');
    $page->checkField('delete_warning_message_entity_types[entity_types][node]');
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');
    $this->drupalGet("/node/{$node1->id()}/edit");
    $assert_session->pageTextContains('Modifications on this form will affect all existing usages of this entity');
    $assert_session->linkExists('existing usages');
    $usage_url = Url::fromRoute('entity_usage.usage_list', [
      'entity_type' => 'node',
      'entity_id' => $node1->id(),
    ])->toString();
    $assert_session->linkByHrefExists($usage_url);
    $this->drupalGet("/node/{$node1->id()}/delete");
    $assert_session->pageTextContains('There are recorded usages of this entity');
    $assert_session->linkExists('recorded usages');
    $assert_session->linkByHrefExists($usage_url);

    // Ensure that delete forms are uncacheable once the message is activated.
    $this->drupalGet("/node/{$node2->id()}/delete");
    $assert_session->pageTextNotContains('There are recorded usages of this entity');

    // If the entity type is configured to have the usage tab available, the
    // warning link should point to the tab route, instead of the generic one.
    $this->drupalGet('/admin/config/entity-usage/settings');
    // Also allow views to have the usage tab visible.
    $node_tab_checkbox = $assert_session->fieldExists('local_task_enabled_entity_types[entity_types][node]');
    $node_tab_checkbox->click();
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');
    $this->drupalGet("/node/{$node1->id()}/edit");
    $assert_session->pageTextContains('Modifications on this form will affect all existing usages of this entity');
    $assert_session->linkExists('existing usages');
    $usage_url = Url::fromRoute("entity.node.entity_usage", [
      'node' => $node1->id(),
    ])->toString();
    $assert_session->linkByHrefExists($usage_url);
    // Re-set tabs to where they were.
    $this->drupalGet('/admin/config/entity-usage/settings');
    $node_tab_checkbox = $assert_session->fieldExists('local_task_enabled_entity_types[entity_types][node]');
    $node_tab_checkbox->click();
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Create a new entity reference field.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_eu_test_related_nodes2',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'eu_test_ct',
      'entity_type' => 'node',
      'field_name' => 'field_eu_test_related_nodes2',
      'label' => 'Related Nodes 2',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['eu_test_ct'],
          'auto_create' => FALSE,
        ],
      ],
    ])->save();
    // Define our widget and formatter for this field.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_nodes2', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_eu_test_related_nodes2', [
        'type' => 'entity_reference_label',
      ])
      ->save();

    // Create Node 3 referencing N2 and N1 one in each field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 3');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    $page->fillField('field_eu_test_related_nodes2[0][target_id]', "Node 2 ({$node2->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been created.');
    $node3 = $this->getLastEntityOfType('node', TRUE);
    // Check that both of these relationships are tracked.
    $usage = $usage_service->listTargets($node3);
    $expected = [
      'node' => [
        $node1->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
        $node2->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes2',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Ensure that node 2 now has the warning.
    $this->drupalGet("/node/{$node2->id()}/delete");
    $assert_session->pageTextContains('There are recorded usages of this entity');

    // If we delete the field storage the usage should update accordingly.
    $storage->delete();
    $usage = $usage_service->listTargets($node3);
    $expected = [
      'node' => [
        $node1->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Edit Node 3, remove the reference to Node 1, check we update usage.
    $this->drupalGet("/node/{$node3->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', '');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been updated');
    // Node 3 isn't referencing any content now.
    $usage = $usage_service->listTargets($node3);
    $this->assertEquals([], $usage);
    // Node 2 isn't referenced by any content now.
    $usage = $usage_service->listSources($node2);
    $this->assertEquals([], $usage);

    // Create node 4 referencing N2 and N3 on the same field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 4');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 2 ({$node2->id()})");
    $add_another_button = $assert_session->elementExists('css', 'input[name="field_eu_test_related_nodes_add_more"]');
    $add_another_button->press();
    $new_input = $assert_session->waitForField('field_eu_test_related_nodes[1][target_id]');
    $this->assertNotNull($new_input);
    $new_input->setValue("Node 3 ({$node3->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 4 has been created.');
    $node4 = $this->getLastEntityOfType('node', TRUE);
    // Check that both of these relationships are tracked.
    $usage = $usage_service->listTargets($node4);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
        $node3->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Deleting one of the targets updates the info accordingly.
    $node2->delete();
    $usage = $usage_service->listTargets($node4);
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Adding the same node twice on the same field counts as 1 usage.
    $this->drupalGet("/node/{$node4->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 3 ({$node3->id()})");
    $page->fillField('field_eu_test_related_nodes[1][target_id]', "Node 3 ({$node3->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 4 has been updated');
    // There should be only one usage record from source N4 -> target N3:
    $usage = $usage_service->listTargets($node4);
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    // There should be only one record the other way around.
    $usage = $usage_service->listSources($node3);
    $expected = [
      'node' => [
        $node4->id() => [
          [
            'source_langcode' => $node4->language()->getId(),
            'source_vid' => $node4->getRevisionId(),
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Deleting the source node should make the usage disappear.
    $node4->delete();
    $usage = $usage_service->listSources($node3);
    $this->assertEquals([], $usage);
  }

  /**
   * Tests the tracking of nodes in link fields.
   */
  public function testLinkTracking(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Add a link field to our test content type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_link1',
      'entity_type' => 'node',
      'type' => 'link',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'eu_test_ct',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ]);
    $field->save();
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_link1', ['type' => 'link_default'])
      ->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_link1', ['type' => 'link'])
      ->save();

    // Create Node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Create Node 2, referencing Node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_link1[0][uri]', "Node 1 ({$node1->id()})");
    $page->fillField('field_link1[0][title]', "Linked text");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');
    $node2 = $this->getLastEntityOfType('node', TRUE);
    // Check that the usage of Node 1 points to Node 2.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          0 => [
            'source_langcode' => 'en',
            'source_vid' => $node2->getRevisionId(),
            'method' => 'link',
            'field_name' => 'field_link1',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Edit Node 2, remove reference.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_link1[0][uri]', '');
    $page->fillField('field_link1[0][title]', '');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    // Verify the usage was released.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Reference Node 1 again, now using the node path instead of label.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_link1[0][uri]', "entity:node/{$node1->id()}");
    $page->fillField('field_link1[0][title]', "Linked text");
    $page->pressButton('Save');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    // Usage now should be there.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          0 => [
            'source_langcode' => 'en',
            'source_vid' => $node2->getRevisionId(),
            'method' => 'link',
            'field_name' => 'field_link1',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    // Delete the source and usage should be released.
    $node2->delete();
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create Node 3 referencing Node 1 with an absolute URL in the link field.
    // Whitelist the local hostname so we can test absolute URLs.
    $current_request = \Drupal::request();
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config->set('site_domains', [$current_request->getHttpHost() . $current_request->getBasePath()]);
    $config->save();
    // Changing site domains requires services to be reconstructed.
    $this->rebuildAll();
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 3');
    $page->fillField('field_link1[0][uri]', $node1->toUrl()->setAbsolute()->toString());
    $assert_session->waitOnAutocomplete();
    $page->fillField('field_link1[0][title]', "Linked text");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been created.');
    $node3 = $this->getLastEntityOfType('node', TRUE);
    // Check that the usage of Node 1 points to Node 2.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node3->id() => [
          0 => [
            'source_langcode' => 'en',
            'source_vid' => $node3->getRevisionId(),
            'method' => 'link',
            'field_name' => 'field_link1',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
  }

}
