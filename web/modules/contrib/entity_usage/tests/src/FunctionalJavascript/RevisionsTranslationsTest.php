<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;

/**
 * Tests tracking of revisions and translations.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class RevisionsTranslationsTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    // To test entities which implement RevisionableInterface but do have
    // revisions.
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // Grant the logged-in user permission to entity_test entities.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, ['view test entity', 'access entity usage statistics']);

    // Allow absolute links to be picked up by entity usage and the node tab to
    // be reached.
    $current_request = \Drupal::request();
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config
      ->set('site_domains', [$current_request->getHttpHost() . $current_request->getBasePath()])
      ->set('local_task_enabled_entity_types', ['node'])
      ->save();
    // Changing site domains requires services to be reconstructed.
    $this->rebuildAll();
  }

  /**
   * Tests the tracking of nodes and revisions.
   */
  public function testRevisionsTracking(): void {
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
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create node 2 referencing node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');
    /** @var \Drupal\node\NodeInterface $node2 */
    $node2 = $this->getLastEntityOfType('node', TRUE);
    $node2_first_revision = $node2->getRevisionId();
    // Check that we correctly registered the relation between N2 and N1.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create new revision of N2, removing reference to N1.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', '');
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision');
    $assert_session->checkboxChecked('Create new revision');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    $node2 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node2->id());

    // We should still have the usage registered by the first revision.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create a third revision of N2, referencing N1 again.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision');
    $assert_session->checkboxChecked('Create new revision');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    $node2 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node2->id());
    $node2_third_revision = $node2->getRevisionId();

    // We should now see usages of both revisions.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_third_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // A new target node.
    $node3 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 3',
    ]);
    $node3->save();

    // Create a new revision of N2 adding a reference to N3 as well.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    $page->fillField('field_eu_test_related_nodes[1][target_id]', "Node 3 ({$node3->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision');
    $assert_session->checkboxChecked('Create new revision');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    $node2 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node2->id());
    $node2_fourth_revision = $node2->getRevisionId();
    // The new usage is there.
    $usage = $usage_service->listSources($node3);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_fourth_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Another revision, removing both references to N1 and N3.
    $this->drupalGet("/node/{$node2->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', '');
    $page->fillField('field_eu_test_related_nodes[1][target_id]', '');
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision');
    $assert_session->checkboxChecked('Create new revision');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');
    $node2 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node2->id());
    // References to N1 and N3 are only previous revisions.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_fourth_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_third_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    $usage = $usage_service->listSources($node3);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2_fourth_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // If we remove 4th revision, the N3 usage should also be deleted.
    $this->drupalGet("/node/{$node2->id()}/revisions/{$node2_fourth_revision}/delete");
    $page->pressButton('Delete');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('has been deleted.');
    $usage = $usage_service->listSources($node3);
    $this->assertEquals([], $usage);

    // Test a revisionable entity type without revisions.
    $entity_test_1 = EntityTest::create([
      'name' => 'Test entity',
      // Use an absolute URL so that tests running Drupal in a subdirectory
      // still work.
      'field_test_text' => '<a href="' . $node1->toUrl()->setAbsolute()->toString() . '">test</a>',
    ]);
    $entity_test_1->save();
    $this->assertInstanceOf(RevisionableInterface::class, $entity_test_1);
    $this->assertNull($entity_test_1->getRevisionId());

    $this->drupalGet("/node/{$node1->id()}/usage");
    $assert_session->pageTextContains('Entity usage information for Node 1');
    // Only two usages; the entity_test entity and node 2.
    $assert_session->elementsCount('xpath', '//table/tbody/tr', 2);
    $first_row_title = $this->xpath('//table/tbody/tr[1]/td[1]')[0];
    $this->assertEquals('Test entity', $first_row_title->getText());
    $first_row_used_in = $this->xpath('//table/tbody/tr[1]/td[6]')[0];
    $this->assertEquals('Default', $first_row_used_in->getText());
    $second_row_title = $this->xpath('//table/tbody/tr[2]/td[1]')[0];
    $this->assertEquals('Node 2', $second_row_title->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[6]')[0];
    $this->assertEquals('Old revision(s)', $second_row_used_in->getText());

    // Create a pending revision of node 2 that links to node 1.
    $node2 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node2->id());
    $node2->setNewRevision();
    $node2->isDefaultRevision(FALSE);
    $node2->field_eu_test_related_nodes->target_id = $node1->id();
    $node2->save();
    $this->drupalGet("/node/{$node1->id()}/usage");
    $assert_session->pageTextContains('Entity usage information for Node 1');
    $assert_session->elementsCount('xpath', '//table/tbody/tr', 2);
    $second_row_title = $this->xpath('//table/tbody/tr[2]/td[1]')[0];
    $this->assertEquals('Node 2', $second_row_title->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[6]/ul/li[1]')[0];
    $this->assertEquals('Pending revision(s) / Draft(s)', $second_row_used_in->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[6]/ul/li[2]')[0];
    $this->assertEquals('Old revision(s)', $second_row_used_in->getText());

    // If we remove a node only being targeted in previous revisions (N1), all
    // usages tracked should also be deleted.
    $node1->delete();
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // If a node has really a lot of revisions, check that we clean all of them
    // upon deletion.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 4');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 2 ({$node2->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 4 has been created.');
    /** @var \Drupal\node\NodeInterface $node4 */
    $node4 = $this->getLastEntityOfType('node', TRUE);
    $num_revisions = 300;
    for ($i = 1; $i < $num_revisions; $i++) {
      $node4->setNewRevision(TRUE);
      $node4->save();
    }
    $usage = $usage_service->listSources($node2);
    $this->assertEquals($num_revisions, count($usage['node'][$node4->id()]));
    // Delete the node through the UI and check all usages are gone.
    $this->drupalGet("/node/{$node4->id()}/delete");
    $page->pressButton('Delete');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('has been deleted.');
    $usage = $usage_service->listSources($node2);
    $this->assertEquals([], $usage);
  }

  /**
   * Tests the tracking of nodes with revisions and translations.
   */
  public function testRevisionsTranslationsTracking(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create some additional languages.
    foreach (['es', 'ca'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    // Let the logged-in user do multi-lingual stuff.
    /** @var \Drupal\user\RoleInterface $authenticated_role */
    $authenticated_role = Role::load('authenticated');
    $authenticated_role->grantPermission('administer content translation');
    $authenticated_role->grantPermission('translate any entity');
    $authenticated_role->grantPermission('create content translations');
    $authenticated_role->grantPermission('administer languages');
    $authenticated_role->grantPermission('administer entity usage');
    $authenticated_role->grantPermission('access entity usage statistics');
    $authenticated_role->save();

    // Set our content type as translatable.
    $this->drupalGet('/admin/config/regional/content-language');
    $page->checkField('entity_types[node]');
    $assert_session->elementExists('css', '#edit-settings-node')->click();
    $page->checkField('settings[node][eu_test_ct][translatable]');
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Settings successfully updated.');

    // Enable the usage page controller for nodes.
    $this->drupalGet('/admin/config/entity-usage/settings');
    $page->checkField('local_task_enabled_entity_types[entity_types][node]');
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Create a target Node 1 in EN.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $assert_session->elementExists('css', 'select[name="langcode[0][value]"]');
    $page->selectFieldOption('langcode[0][value]', 'en');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create a target Node 2 in ES.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $assert_session->elementExists('css', 'select[name="langcode[0][value]"]');
    $page->selectFieldOption('langcode[0][value]', 'es');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');
    /** @var \Drupal\node\NodeInterface $node2 */
    $node2 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node2);
    $this->assertEquals([], $usage);

    // Create Node 3 in EN referencing Node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 3');
    $assert_session->elementExists('css', 'select[name="langcode[0][value]"]');
    $page->selectFieldOption('langcode[0][value]', 'en');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been created.');
    /** @var \Drupal\node\NodeInterface $node3 */
    $node3 = $this->getLastEntityOfType('node', TRUE);
    $node3_first_revision = $node3->getRevisionId();

    // Translate Node 3 to ES but referencing Node 2 instead.
    $this->drupalGet("/es/node/{$node3->id()}/translations/add/en/es");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 2 ({$node2->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision (all languages)');
    $assert_session->checkboxChecked('Create new revision (all languages)');
    $page->pressButton('Save (this translation)');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been updated.');
    $node3 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node3->id());
    $node3_second_revision = $node3->getRevisionId();

    // Check usages are the ones we expect.
    $usage = $usage_service->listSources($node1);
    // Node 1 is referenced from both revisions of Node 3 in EN.
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_second_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    $usage = $usage_service->listSources($node2);
    // Node 2 is referenced from last revision of Node 3 in ES.
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'es',
            'source_vid' => $node3_second_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Check that the usage page of both N1 and N2 show what we expect.
    $this->drupalGet("/node/{$node1->id()}/usage");
    // Only one usage is there, from the EN translation.
    $first_row_title = $this->xpath('//table/tbody/tr[1]/td[1]')[0];
    $this->assertEquals($node3->label(), $first_row_title->getText());
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Related nodes', $first_row_field_label->getText());
    // There's no second row.
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');
    $this->drupalGet("/node/{$node2->id()}/usage");
    // Only one usage is there, from the ES translation.
    $first_row_title = $this->xpath('//table/tbody/tr[1]/td[1]')[0];
    $this->assertEquals($node3->label(), $first_row_title->getText());
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Related nodes', $first_row_field_label->getText());
    $first_row_used_in = $this->xpath('//table/tbody/tr[1]/td[6]')[0];
    $this->assertEquals('Default: ES.', $first_row_used_in->getText());
    // There's no second row.
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');

    // If only a translation is updated, we register correctly the new usage.
    $this->drupalGet("/es/node/{$node3->id()}/edit");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', '');
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision (all languages)');
    $assert_session->checkboxChecked('Create new revision (all languages)');
    $page->pressButton('Save (this translation)');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been updated.');
    $node3 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node3->id());
    $node3_third_revision = $node3->getRevisionId();
    $usage = $usage_service->listSources($node2);
    // Node2 is only being used in the previous ES revision.
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'es',
            'source_vid' => $node3_second_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    // Node3 is being used by all revisions of the EN version.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_third_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_second_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // If a translation is deleted, only that usage is deleted.
    $this->drupalGet("/es/node/{$node3->id()}/edit");
    // Reference Node 2 back in the ES translation.
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 2 ({$node2->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision (all languages)');
    $assert_session->checkboxChecked('Create new revision (all languages)');
    $page->pressButton('Save (this translation)');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been updated.');
    $node3 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node3->id());
    $node3_fourth_revision = $node3->getRevisionId();
    // Node 2 is now referenced from last revision of Node 3 in ES, and from a
    // previous revision of the ES translation.
    $usage = $usage_service->listSources($node2);
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'es',
            'source_vid' => $node3_fourth_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'es',
            'source_vid' => $node3_second_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    // Bye ES translation.
    $this->drupalGet("/es/node/{$node3->id()}/delete");
    $assert_session->pageTextContains('Are you sure you want to delete the Spanish translation of the content');
    $page->pressButton('Delete Spanish translation');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('has been deleted');
    // Node 2 usage shouldn't be registered anymore.
    $usage = $usage_service->listSources($node2);
    $this->assertEquals([], $usage);
    // Node is 1 still tracked in all revisions in EN.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_fourth_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_third_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_second_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
          [
            'source_langcode' => 'en',
            'source_vid' => $node3_first_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // If the main language is deleted, all records are deleted.
    $this->drupalGet("/es/node/{$node3->id()}/translations/add/en/es");
    // We will add a ES translation again, so we make sure it is deleted later.
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 2 ({$node2->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision (all languages)');
    $assert_session->checkboxChecked('Create new revision (all languages)');
    $page->pressButton('Save (this translation)');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 3 has been updated.');
    $node3 = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node3->id());
    $node3_fifth_revision = $node3->getRevisionId();
    // The usage from the translation is there.
    $usage = $usage_service->listSources($node2);
    $expected = [
      'node' => [
        $node3->id() => [
          [
            'source_langcode' => 'es',
            'source_vid' => $node3_fifth_revision,
            'method' => 'entity_reference',
            'field_name' => 'field_eu_test_related_nodes',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
    // Bye source node in main language.
    $this->drupalGet("/node/{$node3->id()}/delete");
    $assert_session->pageTextContains('The following content item translations will be deleted');
    $page->pressButton('Delete all translations');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('has been deleted');
    // Everything is wiped out.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);
    $usage = $usage_service->listSources($node2);
    $this->assertEquals([], $usage);
  }

}
