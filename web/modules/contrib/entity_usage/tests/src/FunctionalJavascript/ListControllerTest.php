<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;

/**
 * Tests the page listing the usage of a given entity.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class ListControllerTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Grant the logged-in user permission to see the statistics page.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, ['access entity usage statistics']);
  }

  /**
   * Tests the page listing the usage of entities.
   *
   * @covers \Drupal\entity_usage\Controller\ListUsageController::listUsagePage
   */
  public function testListController(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    /** @var \Drupal\node\NodeInterface $node1 */
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Create node 2 referencing node 1 using reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', 'Node 1 (1)');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been created.');
    $node2 = $this->getLastEntityOfType('node', TRUE);

    // Create node 3 also referencing node 1 in an embed text field.
    $uuid_node1 = $node1->uuid();
    $embedded_text = '<drupal-entity data-embed-button="node" data-entity-embed-display="entity_reference:entity_reference_label" data-entity-embed-display-settings="{&quot;link&quot;:1}" data-entity-type="node" data-entity-uuid="' . $uuid_node1 . '"></drupal-entity>';
    $node3 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 3',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node3->save();

    // Visit the page that tracks usage of node 1 and check everything is there.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Entity usage information for Node 1');

    // Check table headers are present.
    $assert_session->pageTextContains('Entity');
    $assert_session->pageTextContains('Type');
    $assert_session->pageTextContains('Language');
    $assert_session->pageTextContains('Field name');
    $assert_session->pageTextContains('Status');

    // Make sure that all elements of the table are the expected ones.
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $this->assertStringContainsString($node3->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $first_row_type->getText());
    $first_row_langcode = $this->xpath('//table/tbody/tr[1]/td[3]')[0];
    $this->assertEquals('English', $first_row_langcode->getText());
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Text', $first_row_field_label->getText());
    $first_row_status = $this->xpath('//table/tbody/tr[1]/td[5]')[0];
    $this->assertEquals('Published', $first_row_status->getText());

    $second_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[2]/td[1]/a');
    $this->assertEquals('Node 2', $second_row_title_link->getText());
    $this->assertStringContainsString($node2->toUrl()->toString(), $second_row_title_link->getAttribute('href'));
    $second_row_type = $this->xpath('//table/tbody/tr[2]/td[2]')[0];
    $this->assertEquals('Content: Entity Usage test content', $second_row_type->getText());
    $second_row_langcode = $this->xpath('//table/tbody/tr[2]/td[3]')[0];
    $this->assertEquals('English', $second_row_langcode->getText());
    $second_row_field_label = $this->xpath('//table/tbody/tr[2]/td[4]')[0];
    $this->assertEquals('Related nodes', $second_row_field_label->getText());
    $second_row_status = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('Published', $second_row_status->getText());

    // If we unpublish Node 2 its status is correctly reflected.
    /** @var \Drupal\node\NodeInterface $node2 */
    $node2->setUnpublished()->save();
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $second_row_status = $this->xpath('//table/tbody/tr[2]/td[5]')[0];
    $this->assertEquals('Unpublished', $second_row_status->getText());

    // Artificially create some garbage in the database and make sure it doesn't
    // show up on the usage page.
    \Drupal::database()->insert('entity_usage')
      ->fields([
        'target_id' => $node1->id(),
        'target_type' => $node1->getEntityTypeId(),
        'source_id' => '1234',
        'source_type' => 'user',
        'source_langcode' => 'en',
        'source_vid' => '5678',
        'method' => 'entity_reference',
        'field_name' => 'field_foo',
        'count' => '1',
      ])
      ->execute();
    // Check the usage is there.
    $usage = \Drupal::service('entity_usage.usage')->listSources($node1);
    $this->assertTrue(!empty($usage['user']));
    // Check the usage list skips it when showing results.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Entity usage information for Node 1');
    $assert_session->elementNotContains('css', 'table', '1234');
    $assert_session->elementNotContains('css', 'table', 'user');
    $assert_session->elementNotContains('css', 'table', '5678');
    $assert_session->elementNotContains('css', 'table', 'field_foo');

    // When all usages are shown on their default revisions, we don't see the
    // extra column.
    $assert_session->pageTextNotContains('Used in');
    $assert_session->pageTextNotContains('Old revision(s)');
    $assert_session->pageTextNotContains('Pending revision(s) / Draft(s)');
    $assert_session->pageTextNotContains('Default:');

    // If some sources reference our entity in a previous revision, an
    // additional column is shown.
    // @phpstan-ignore-next-line
    $node2->field_eu_test_related_nodes = NULL;
    $node2->setNewRevision();
    $node2->save();
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Used in');
    $second_row_used_in = $this->xpath('//table/tbody/tr[1]/td[6]')[0];
    $this->assertEquals('Default', $second_row_used_in->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[6]')[0];
    $this->assertEquals('Old revision(s)', $second_row_used_in->getText());

    // Make sure we only have 2 rows (so no previous revision shows up).
    $this->assertEquals(2, count($this->xpath('//table/tbody/tr')));

    // Create some additional languages.
    foreach (['es'] as $langcode) {
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

    // Translate $node2 and check its translation doesn't show up.
    $this->drupalGet("/es/node/{$node2->id()}/translations/add/en/es");
    $page->fillField('field_eu_test_related_nodes[0][target_id]', "Node 1 ({$node1->id()})");
    // Ensure we are creating a new revision.
    $revision_tab = $page->find('css', 'a[href="#edit-revision-information"]');
    $revision_tab->click();
    $page->checkField('Create new revision (all languages)');
    $assert_session->checkboxChecked('Create new revision (all languages)');
    $page->pressButton('Save (this translation)');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 2 has been updated.');

    // Usage now should be the same as before.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->pageTextContains('Used in');
    $first_row_used_in = $this->xpath('//table/tbody/tr[1]/td[6]')[0];
    $this->assertEquals('Default', $first_row_used_in->getText());
    $second_row_used_in = $this->xpath('//table/tbody/tr[2]/td[6]')[0];
    $this->assertEquals('Default: ES. Old revision(s)', $second_row_used_in->getText());
    $this->assertEquals(2, count($this->xpath('//table/tbody/tr')));

    // Verify that it's possible to control the number of items per page.
    // Initially we have no pager since two rows fit in one page.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    $assert_session->elementNotExists('css', 'ul.pager__items');
    $this->drupalGet('/admin/config/entity-usage/settings');
    // Set items per page to 1.
    $page->find('css', 'input[name="usage_controller_items_per_page"]')
      ->setValue('1');
    $page->pressButton('Save configuration');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The configuration options have been saved.');
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}");
    // Pager is there.
    $pager_element = $assert_session->elementExists('css', 'ul.pager__items');
    // First node is on the first page, the second node on the next page.
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 3', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');
    $pager_element->find('css', '.pager__item--next a')->click();
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals('Node 2', $first_row_title_link->getText());
    $assert_session->elementNotExists('xpath', '//table/tbody/tr[2]');

    // Set reference on bundleless user entity referencing node 1.
    $this->loggedInUser->set('field_eu_test_related_nodes', [
      'target_id' => $node1->id(),
    ])->save();

    // Check this reference shows up on usage page, without bundle label.
    $this->drupalGet("/admin/content/entity-usage/node/{$node1->id()}", ['query' => ['page' => '2']]);
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($this->loggedInUser->getDisplayName(), $first_row_title_link->getText());
    $this->assertStringContainsString($this->loggedInUser->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_type = $this->xpath('//table/tbody/tr[1]/td[2]')[0];
    $this->assertEquals('User', $first_row_type->getText());
  }

}
