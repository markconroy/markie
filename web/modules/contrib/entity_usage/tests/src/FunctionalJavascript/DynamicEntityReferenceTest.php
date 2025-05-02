<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the integration with the Dynamic Entity Reference module.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class DynamicEntityReferenceTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_entity_reference',
  ];

  /**
   * Tests the tracking of entities through dynamic entity reference fields.
   */
  public function testDynamicEntityReferenceTracking(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Add a dynamic entity reference field to our test content type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_der1',
      'entity_type' => 'node',
      'type' => 'dynamic_entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'node' => 'node',
          'user' => 'user',
        ],
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'eu_test_ct',
      'settings' => [
        'node' => [
          'handler' => 'default:node',
          'handler_settings' => [
            'target_bundles' => [
              'eu_test_ct' => 'eu_test_ct',
            ],
            'sort' => [
              'field' => '_none',
            ],
            'auto_create' => FALSE,
            'auto_create_bundle' => '',
          ],
        ],
        'user' => [
          'handler' => 'default:user',
          'handler_settings' => [
            'include_anonymous' => FALSE,
            'filter' => [
              'type' => '_none',
            ],
            'target_bundles' => NULL,
            'sort' => [
              'field' => '_none',
            ],
            'auto_create' => FALSE,
          ],
        ],
      ],
    ]);
    $field->save();
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_der1', ['type' => 'dynamic_entity_reference_default'])
      ->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'eu_test_ct', 'default')
      ->setComponent('field_der1', ['type' => 'dynamic_entity_reference_label'])
      ->save();

    // Enable users to be tracked as target.
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config->set('track_enabled_target_entity_types', ['node', 'user']);
    $config->save();

    // Create Node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Create user 1.
    $user1 = $this->drupalCreateUser([]);

    // Create Node 2, referencing Node 1 and User 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->selectFieldOption('field_der1[0][target_type]', 'node');
    $page->fillField('field_der1[0][target_id]', "Node 1 ({$node1->id()})");
    $page->pressButton('edit-field-der1-add-more');
    $this->waitForAjaxToFinish();
    $page->selectFieldOption('field_der1[1][target_type]', 'user');
    $page->fillField('field_der1[1][target_id]', "{$user1->getDisplayName()} ({$user1->id()})");
    $this->saveHtmlOutput();
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
            'method' => 'dynamic_entity_reference',
            'field_name' => 'field_der1',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    // Check that the usage of User 1 points also to Node 2.
    $usage = $usage_service->listSources($user1);
    $this->assertEquals($expected, $usage);

    // Edit Node 2, remove one of the references.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_der1[0][target_id]', '');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    // The node usage was released, the user was not.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);
    $expected = [
      'node' => [
        $node2->id() => [
          0 => [
            'source_langcode' => 'en',
            'source_vid' => $node2->getRevisionId(),
            'method' => 'dynamic_entity_reference',
            'field_name' => 'field_der1',
            'count' => 1,
          ],
        ],
      ],
    ];
    $usage = $usage_service->listSources($user1);
    $this->assertEquals($expected, $usage);

    // Delete the source node, the user reference should also have been cleared.
    $node2->delete();
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);
    $usage = $usage_service->listSources($user1);
    $this->assertEquals([], $usage);
  }

}
